<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingOrder;
use App\Models\BookingRescheduleLog;
use App\Models\Court;
use App\Models\OperatingHours;
use App\Services\BookingOrderService;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings, protected BookingOrderService $bookingOrders) {}

    public function index(Request $request)
    {
        $bookings = $this->representativeBookings()
            ->with([
                'court', 'user:id,name,phone,email', 'slots', 'statusLogs.changedBy:id,name', 'matches', 'rescheduleLogs.changedBy:id,name',
                'bookingOrder' => fn ($q) => $q->withCount('bookings'),
                'bookingOrder.bookings' => fn ($q) => $q->orderBy('id'),
                'bookingOrder.bookings.slots',
                'bookingOrder.bookings.court',
                'bookingOrder.bookings.rescheduleLogs',
                'bookingOrder.bookings.splitFrom:id,booking_code',
                'bookingOrder.bookings.splitSiblings:id,booking_code,split_from_booking_id',
            ])
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
            // cancelled because they got rescheduled (rained out, moved to a
            // new date) - any other cancellation (rejected, payment expired,
            // etc.) never actually held the slot in a way staff care about
            // here. Legacy: rescheduledTo only ever matches bookings moved
            // via the old cancel-and-recreate mechanism, before reschedules
            // started updating the same row in place.
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')
                ->orWhereHas('rescheduledTo'))
            ->with([
                'court',
                'user:id,name,phone,email',
                'slots' => fn ($q) => $q->whereDate('slot_date', $date)->orderBy('start_time'),
                'statusLogs.changedBy:id,name',
                'matches',
                'rescheduleLogs.changedBy:id,name',
                'bookingOrder' => fn ($q) => $q->withCount('bookings'),
            ])
            ->get()
            ->sortBy(fn (Booking $b) => $b->slots->first()?->start_time)
            ->values();

        // Bookings that USED to be on this day but got rescheduled away -
        // otherwise a moved booking just silently vanishes from its old day
        // with no trace, which reads as "never existed" rather than "moved".
        $rescheduledAway = BookingRescheduleLog::query()
            ->whereDate('from_slot_date', $date)
            ->with(['booking:id,booking_code,guest_name,guest_phone,user_id', 'booking.user:id,name,phone'])
            ->latest('created_at')
            ->get()
            ->unique('booking_id')
            ->values();

        return view('admin.bookings.schedule', [
            'date' => $date,
            'bookings' => $bookings,
            'rescheduledAway' => $rescheduledAway,
        ]);
    }

    /**
     * Front-desk booking form for a brand-new walk-in booking.
     */
    public function create(Request $request)
    {
        $this->authorizeCanManageBookings();

        return view('admin.bookings.create', [
            'courts' => Court::where('is_active', true)->orderBy('name')->get(['id', 'name', 'status']),
            'periodBoundaries' => OperatingHours::current()->periodBoundaries(),
            'periodEnds' => OperatingHours::current()->periodEnds(),
            'prefill' => $request->only(['guest_name', 'guest_phone', 'guest_email', 'court_id']),
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
        ]);

        $court = Court::findOrFail($data['court_id']);

        $guest = [
            'name' => $data['guest_name'],
            'phone' => $data['guest_phone'],
            'email' => $data['guest_email'] ?? null,
        ];

        // Admin walk-in bookings skip payment entirely, so there's no
        // BookingOrder/payment wrapper here - a non-contiguous pick just
        // becomes several immediately-confirmed bookings in one go.
        $groups = $this->bookings->groupContiguousSlotIds($data['court_slot_ids']);
        $created = collect();

        try {
            foreach ($groups as $group) {
                $created->push($this->bookings->createConfirmedBooking(null, $court, $group, $guest, Auth::user()));
            }
        } catch (NonContiguousSlotsException|SlotUnavailableException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        }

        $codes = $created->pluck('booking_code')->implode(', ');
        $status = $created->count() > 1
            ? "{$created->count()} bookings created and confirmed for {$created->first()->contactName()}: {$codes}."
            : "Booking {$codes} created and confirmed for {$created->first()->contactName()}.";

        return redirect()->route('admin.bookings.index')->with('status', $status);
    }

    /**
     * "Reschedule" on a multi-session order lands here first: which of the
     * order's sessions actually needs to move? Each session links on to
     * editReschedule() below for that one specific booking - reschedules
     * still only ever touch one session at a time, this just saves the
     * admin from having to expand the order row in the table first to find
     * the right one.
     */
    public function selectReschedule(BookingOrder $order)
    {
        $this->authorizeCanManageBookings();

        return view('admin.bookings.reschedule-select', [
            'order' => $order->load(['bookings.slots', 'bookings.court']),
        ]);
    }

    /**
     * Reschedule landing page for ONE existing session/booking - moves it
     * to a new date/time in place (same id, same booking_code, same
     * status) instead of cancelling it and creating a replacement. See
     * BookingService::rescheduleBooking().
     */
    public function editReschedule(Booking $booking)
    {
        $this->authorizeCanManageBookings();

        abort_unless($this->isReschedulable($booking), 422, 'This booking cannot be rescheduled.');

        return view('admin.bookings.reschedule', [
            'booking' => $booking->load('court', 'slots'),
            'courts' => Court::where('is_active', true)->orderBy('name')->get(['id', 'name', 'status']),
            'periodBoundaries' => OperatingHours::current()->periodBoundaries(),
            'periodEnds' => OperatingHours::current()->periodEnds(),
        ]);
    }

    public function updateReschedule(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'court_slot_ids' => ['required', 'array', 'min:1', 'max:12'],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $court = Court::findOrFail($data['court_id']);

        $groups = $this->bookings->groupContiguousSlotIds($data['court_slot_ids']);

        if (count($groups) !== 1) {
            return back()->withErrors(['court_slot_ids' => 'Pick one contiguous block of time to move this session to.'])->withInput();
        }

        try {
            $booking = $this->bookings->rescheduleBooking($booking, $court, $groups[0], Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException|NonContiguousSlotsException|SlotUnavailableException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        }

        $log = $booking->rescheduleLogs->last();
        $status = "Booking {$booking->booking_code} rescheduled from {$log->from_slot_date->format('M j')}, ".\Illuminate\Support\Carbon::parse($log->from_start_time)->format('g:i A').'–'.\Illuminate\Support\Carbon::parse($log->from_end_time)->format('g:i A')
            ." to {$log->to_slot_date->format('M j')}, ".\Illuminate\Support\Carbon::parse($log->to_start_time)->format('g:i A').'–'.\Illuminate\Support\Carbon::parse($log->to_end_time)->format('g:i A').'.';

        return redirect()->route('admin.bookings.index')->with('status', $status);
    }

    /**
     * Partial reschedule: only some of a booking's hours are affected (e.g.
     * rain hit just the 9-10am hour of an 8-11am booking). The unaffected
     * hours split off into sibling booking(s) that keep their original
     * date/time; this booking keeps its id/code and moves just the
     * affected hours. See BookingService::splitAndReschedule().
     */
    public function updateSplitReschedule(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'affected_court_slot_ids' => ['required', 'array', 'min:1'],
            'affected_court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'court_slot_ids' => ['required', 'array', 'min:1', 'max:12'],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $court = Court::findOrFail($data['court_id']);

        $affectedGroups = $this->bookings->groupContiguousSlotIds($data['affected_court_slot_ids']);
        if (count($affectedGroups) !== 1) {
            return back()->withErrors(['affected_court_slot_ids' => 'Pick one contiguous block of hours that were affected.'])->withInput();
        }

        $destGroups = $this->bookings->groupContiguousSlotIds($data['court_slot_ids']);
        if (count($destGroups) !== 1) {
            return back()->withErrors(['court_slot_ids' => 'Pick one contiguous block of time to move the affected hours to.'])->withInput();
        }

        try {
            $result = $this->bookings->splitAndReschedule(
                $booking, $affectedGroups[0], $court, $destGroups[0], Auth::user(), $data['reason'] ?? null
            );
        } catch (InvalidBookingTransitionException|NonContiguousSlotsException|SlotUnavailableException|\InvalidArgumentException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        }

        $moved = $result['moved'];
        $remainderCount = $result['remainder']->count();
        $log = $moved->rescheduleLogs->last();

        $status = "Booking {$moved->booking_code}: moved {$log->from_slot_date->format('M j')}, ".\Illuminate\Support\Carbon::parse($log->from_start_time)->format('g:i A').'–'.\Illuminate\Support\Carbon::parse($log->from_end_time)->format('g:i A')
            ." to {$log->to_slot_date->format('M j')}, ".\Illuminate\Support\Carbon::parse($log->to_start_time)->format('g:i A').'–'.\Illuminate\Support\Carbon::parse($log->to_end_time)->format('g:i A')
            .($remainderCount ? '; the rest stayed booked as originally scheduled ('.$result['remainder']->pluck('booking_code')->implode(', ').').' : '.');

        return redirect()->route('admin.bookings.index')->with('status', $status);
    }

    /**
     * Incremental polling endpoint for the admin bookings screen. Returns
     * bookings with id > last_id (new arrivals), plus a status/reference/
     * proof snapshot for whichever ids the page is currently displaying
     * (watch_ids) - a customer submitting a GCash reference or payment
     * proof on an already-listed pending booking doesn't create a new row,
     * so without this the table would keep showing "Not submitted" until
     * someone manually refreshed. Session-authenticated (not Sanctum),
     * since the admin panel is a plain server-rendered web app, not an SPA/
     * API client.
     */
    public function latest(Request $request)
    {
        $lastId = $request->integer('last_id', 0);

        // Only representative bookings (see representativeBookings()) count
        // as "new arrivals" here - otherwise an order's non-representative
        // sessions (never shown as their own row) would keep tripping the
        // "new booking" banner and reload loop every poll, since the page's
        // lastId can never catch up to an id it never displays.
        $bookings = $this->representativeBookings()
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

        $watchIds = collect(explode(',', (string) $request->query('watch_ids')))
            ->map(fn ($id) => (int) $id)
            ->filter();

        $updates = $watchIds->isNotEmpty()
            ? Booking::whereIn('id', $watchIds)
                ->get(['id', 'status', 'gcash_reference', 'payment_proof_path'])
                ->map(fn (Booking $b) => [
                    'id' => $b->id,
                    'status' => $b->status,
                    'has_reference' => filled($b->gcash_reference),
                    'has_proof' => filled($b->payment_proof_path),
                ])
            : collect();

        return response()
            ->json(['data' => $bookings, 'updates' => $updates])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Polled by the admin sidebar to badge "Bookings" with the count of
     * bookings still awaiting an approve/reject decision.
     */
    public function pendingCount()
    {
        // Representative bookings only, so a multi-session order pending
        // review badges as 1 item to act on - not one per session, which
        // would overcount relative to what the list actually shows.
        return response()
            ->json(['pending_count' => $this->representativeBookings()->where('status', 'pending_payment')->count()])
            ->header('Cache-Control', 'no-store');
    }

    public function approve(Booking $booking)
    {
        $this->authorizeCanManageBookings();

        try {
            if ($booking->booking_order_id) {
                $order = $this->bookingOrders->approve($booking->bookingOrder, Auth::user());

                return back()->with('status', "Order confirmed — {$order->bookings->count()} booking(s).");
            }

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
            if ($booking->booking_order_id) {
                $order = $this->bookingOrders->reject($booking->bookingOrder, Auth::user(), $data['reason'] ?? null);

                return back()->with('status', "Order rejected — {$order->bookings->count()} booking(s).");
            }

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
            if ($booking->booking_order_id) {
                $order = $this->bookingOrders->cancel($booking->bookingOrder, Auth::user(), $data['reason'] ?? null);

                return back()->with('status', "Order cancelled — {$order->bookings->count()} booking(s).");
            }

            $this->bookings->cancel($booking, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->booking_code} cancelled.");
    }

    /**
     * Multi-session orders are represented by a single row (their earliest
     * booking) wherever bookings are listed - the other sessions in the
     * order are shown nested inside that row instead of as separate rows,
     * so an order reads as the one purchase it actually is.
     */
    protected function representativeBookings(): \Illuminate\Database\Eloquent\Builder
    {
        return Booking::query()->where(function ($q) {
            $q->whereNull('booking_order_id')
                ->orWhereIn('id', function ($sub) {
                    $sub->selectRaw('MIN(id)')->from('bookings')->whereNotNull('booking_order_id')->groupBy('booking_order_id');
                });
        });
    }

    protected function authorizeCanManageBookings(): void
    {
        abort_unless(Auth::user()->isAdmin() || Auth::user()->isStaff(), 403);
    }

    /**
     * Mirrors the guards in BookingService::rescheduleBooking() so the
     * "Reschedule" button/link never shows up for something that would
     * just error - a session that's already over, already decided
     * (rejected/cancelled/completed), or tied to an active Open Play room.
     */
    protected function isReschedulable(Booking $booking): bool
    {
        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            return false;
        }

        $lastDate = $booking->slots->max('slot_date') ?? $booking->slots()->max('slot_date');

        if (! $lastDate || \Illuminate\Support\Carbon::parse($lastDate)->lt(today())) {
            return false;
        }

        return ! $booking->openPlayRoomCourt()->exists();
    }
}
