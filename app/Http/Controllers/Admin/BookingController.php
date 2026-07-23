<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\OperatingHours;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request)
    {
        $bookings = Booking::query()
            ->with(['court', 'user:id,name,phone,email', 'slots', 'statusLogs.changedBy:id,name', 'matches', 'rebookedFrom:id,booking_code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('court_id'), fn ($q) => $q->where('court_id', $request->integer('court_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('booking_code', 'like', "%{$term}%")
                        ->orWhere('guest_name', 'like', "%{$term}%")
                        ->orWhere('guest_phone', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'courts' => Court::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'court_id', 'search']),
        ]);
    }

    /**
     * Day-schedule view: pick a date on the calendar and see every booked
     * slot that day, with the time and who booked it.
     */
    public function schedule(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))
            : Carbon::today();

        $bookings = Booking::query()
            ->whereHas('slots', fn ($q) => $q->whereDate('slot_date', $date))
            // Cancelled bookings only belong on the day view if they were
            // cancelled because they got rebooked (rained out, rescheduled) -
            // any other cancellation (rejected, payment expired, etc.) never
            // actually held the slot in a way staff care about here.
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')
                ->orWhereHas('rebookedTo'))
            ->with([
                'court',
                'user:id,name,phone,email',
                'slots' => fn ($q) => $q->whereDate('slot_date', $date)->orderBy('start_time'),
                'statusLogs.changedBy:id,name',
                'matches',
                'rebookedFrom:id,booking_code',
                'rebookedFrom.slots' => fn ($q) => $q->orderBy('slot_date')->orderBy('start_time'),
            ])
            ->get()
            ->sortBy(fn (Booking $b) => $b->slots->first()?->start_time)
            ->values();

        return view('admin.bookings.schedule', [
            'date' => $date,
            'bookings' => $bookings,
        ]);
    }

    /**
     * Front-desk booking form. Also doubles as the "Rebook" landing page -
     * ?guest_name=&guest_phone=&guest_email=&court_id=&hours=&rebook_from=
     * prefill the form from a previous booking (e.g. rained out, customer's
     * already-paid slot moves to a new date) so the admin doesn't retype the
     * customer's details, and the new booking stays linked to the old one.
     */
    public function create(Request $request)
    {
        $this->authorizeCanManageBookings();

        return view('admin.bookings.create', [
            'courts' => Court::where('is_active', true)->orderBy('name')->get(['id', 'name', 'status']),
            'periodBoundaries' => OperatingHours::current()->periodBoundaries(),
            'prefill' => $request->only(['guest_name', 'guest_phone', 'guest_email', 'court_id', 'hours', 'rebook_from']),
            'rebookingFrom' => $request->filled('rebook_from') ? Booking::find($request->integer('rebook_from')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            // Higher than the customer self-service cap (6) - staff may
            // legitimately book a full day for an event/tournament rental.
            'court_slot_ids' => ['required', 'array', 'min:1', 'max:24'],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:150'],
            'rebooked_from_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);

        $court = Court::findOrFail($data['court_id']);
        $rebookedFrom = isset($data['rebooked_from_id']) ? Booking::find($data['rebooked_from_id']) : null;

        $guest = [
            'name' => $data['guest_name'],
            'phone' => $data['guest_phone'],
            'email' => $data['guest_email'] ?? null,
        ];

        try {
            $booking = $this->bookings->createConfirmedBooking(null, $court, $data['court_slot_ids'], $guest, Auth::user(), $rebookedFrom);
        } catch (NonContiguousSlotsException|SlotUnavailableException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        }

        $status = "Booking {$booking->booking_code} created and confirmed for {$booking->contactName()}.";
        if ($rebookedFrom) {
            $status .= " Original booking {$rebookedFrom->booking_code} was cancelled as rescheduled.";
        }

        return redirect()->route('admin.bookings.index')->with('status', $status);
    }

    /**
     * Incremental polling endpoint for the admin bookings screen. Returns only
     * bookings with id > last_id, so the client can detect new arrivals without
     * refetching the whole list. Session-authenticated (not Sanctum), since the
     * admin panel is a plain server-rendered web app, not an SPA/API client.
     */
    public function latest(Request $request)
    {
        $lastId = $request->integer('last_id', 0);

        $bookings = Booking::query()
            ->with(['court:id,name'])
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'booking_code', 'court_id', 'status', 'total_price', 'created_at'])
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'court' => $b->court->name,
                'status' => $b->status,
                'total_price' => $b->total_price,
                'contact' => $b->contactName(),
            ]);

        return response()
            ->json(['data' => $bookings])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Polled by the admin sidebar to badge "Bookings" with the count of
     * bookings still awaiting an approve/reject decision.
     */
    public function pendingCount()
    {
        return response()
            ->json(['pending_count' => Booking::where('status', 'pending_payment')->count()])
            ->header('Cache-Control', 'no-store');
    }

    public function approve(Booking $booking)
    {
        $this->authorizeCanManageBookings();

        try {
            $this->bookings->approve($booking, Auth::user());
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->booking_code} confirmed.");
    }

    public function reject(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->bookings->reject($booking, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->booking_code} rejected.");
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->bookings->cancel($booking, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->booking_code} cancelled.");
    }

    protected function authorizeCanManageBookings(): void
    {
        abort_unless(Auth::user()->isAdmin() || Auth::user()->isStaff(), 403);
    }
}
