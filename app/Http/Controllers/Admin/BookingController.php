<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingHold;
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
                // Every hold this booking has ever been through, not just an
                // active one - the History timeline needs past (resolved)
                // holds too, to show what time was held even after it's
                // since been rescheduled elsewhere. Callers wanting just the
                // currently-active hold filter this loaded collection with
                // ->whereNull('resolved_at') rather than re-querying.
                'holds' => fn ($q) => $q->with('fromCourt:id,name')->orderByDesc('created_at'),
                'bookingOrder' => fn ($q) => $q->withCount('bookings'),
                'bookingOrder.bookings' => fn ($q) => $q->orderBy('id'),
                'bookingOrder.bookings.slots',
                'bookingOrder.bookings.court',
                'bookingOrder.bookings.rescheduleLogs',
                'bookingOrder.bookings.splitFrom:id,booking_code,status',
                'bookingOrder.bookings.splitFrom.holds' => fn ($q) => $q->whereNull('resolved_at'),
                'bookingOrder.bookings.splitSiblings:id,booking_code,split_from_booking_id',
                'bookingOrder.bookings.holds' => fn ($q) => $q->orderByDesc('created_at'),
            ])
            // Held bookings have zero slots and are shown in their own
            // "Held Bookings" list instead - only surface them here if the
            // admin explicitly filters for them (they'd render oddly against
            // a template built around $booking->slots->first() otherwise).
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->where('status', '!=', 'on_hold'))
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
                'splitFrom:id,booking_code,status',
                'splitFrom.holds' => fn ($q) => $q->whereNull('resolved_at'),
                'splitSiblings:id,booking_code,split_from_booking_id',
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
            'guest_phone' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'guest_email' => ['nullable', 'email', 'max:150'],
        ]);

        $court = Court::findOrFail($data['court_id']);

        $guest = [
            'name' => $data['guest_name'],
            'phone' => $data['guest_phone'] ?? null,
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
     * Edit page for a booking a guest entered themselves - rename their
     * contact details, or add/remove hours. See Booking::isAdminEditable().
     */
    public function editDetails(Booking $booking)
    {
        $this->authorizeCanManageBookings();

        abort_unless($booking->isAdminEditable(), 422, 'This booking cannot be edited.');

        $booking->load(['court', 'slots' => fn ($q) => $q->orderBy('start_time')]);

        return view('admin.bookings.edit', [
            'booking' => $booking,
        ]);
    }

    public function updateGuestDetails(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'guest_email' => ['nullable', 'email', 'max:150'],
        ]);

        try {
            $this->bookings->renameGuest($booking, $data['guest_name'], $data['guest_phone'] ?? null, $data['guest_email'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['guest_name' => $e->getMessage()]);
        }

        return redirect()->route('admin.bookings.edit', $booking)->with('status', "Booking {$booking->booking_code} details updated.");
    }

    /**
     * Combined edit page for an order with multiple dates/times (e.g. a
     * client booked several sessions at once via "Add multiple dates &
     * times") - one screen listing every session in the order, instead of
     * having to open each date's own edit page separately. Renaming here
     * updates every editable session in the order at once; adding more
     * dates and per-date hour edits still reuse the existing single-booking
     * routes (addSessions/edit/cancel), just pointed at whichever session in
     * the order is editable.
     */
    public function editOrder(BookingOrder $order)
    {
        $this->authorizeCanManageBookings();

        $order->load(['bookings' => fn ($q) => $q->orderBy('id'), 'bookings.slots' => fn ($q) => $q->orderBy('start_time'), 'bookings.court']);

        abort_unless($order->bookings->contains(fn (Booking $b) => $b->isAdminEditable()), 422, 'No editable sessions in this booking.');

        return view('admin.bookings.edit-order', [
            'order' => $order,
            'anchor' => $order->bookings->first(fn (Booking $b) => $b->isAdminEditable()),
        ]);
    }

    /**
     * Renames the guest across every editable session in the order at once
     * - sessions the admin didn't walk in themselves (or that are no longer
     * active) are left untouched rather than erroring the whole request.
     */
    public function updateOrderGuestDetails(Request $request, BookingOrder $order)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'guest_email' => ['nullable', 'email', 'max:150'],
        ]);

        $order->load('bookings');
        $updated = 0;

        foreach ($order->bookings as $session) {
            if ($session->isAdminEditable()) {
                $this->bookings->renameGuest($session, $data['guest_name'], $data['guest_phone'] ?? null, $data['guest_email'] ?? null);
                $updated++;
            }
        }

        return redirect()->route('admin.bookings.edit-order', $order)->with('status', "Updated guest details on {$updated} session(s).");
    }

    public function removeTime(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'court_slot_ids' => ['required', 'array', 'min:1'],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
        ]);

        try {
            $booking = $this->bookings->removeSlotsFromBooking($booking, $data['court_slot_ids']);
        } catch (InvalidBookingTransitionException|SlotUnavailableException|\InvalidArgumentException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()]);
        }

        return redirect()->route('admin.bookings.edit', $booking)->with('status', "Removed time from booking {$booking->booking_code}.");
    }

    /**
     * Adds several hand-picked (date, hour-block) sessions onto a booking in
     * one go - each row can have its own time (e.g. Aug 18 6-8pm, Aug 21
     * 4-11pm). All-or-nothing: any row that's no longer available aborts the
     * whole batch with a clear error instead of silently dropping it. See
     * BookingService::addMultipleSessions().
     */
    public function addSessions(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.court_slot_ids' => ['required', 'array', 'min:1'],
            'sessions.*.court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
        ]);

        try {
            $created = $this->bookings->addMultipleSessions($booking, $data['sessions'], Auth::user());
        } catch (InvalidBookingTransitionException|SlotUnavailableException|NonContiguousSlotsException|\InvalidArgumentException $e) {
            return back()->withErrors(['sessions' => $e->getMessage()]);
        }

        $status = "Added {$created->count()} new date(s) to {$booking->booking_code}.";

        // addMultipleSessions() always wraps the booking in an order (see
        // BookingService::ensureOrder()) - once it has sibling dates, the
        // order-level edit page is the right place to land back on (shows
        // every date together), not the single-session edit page this
        // request's anchor booking happens to be attached to.
        $orderId = $created->first()->booking_order_id ?? $booking->booking_order_id;

        if ($orderId) {
            return redirect()->route('admin.bookings.edit-order', $orderId)->with('status', $status);
        }

        return redirect()->route('admin.bookings.edit', $booking)->with('status', $status);
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
            'order' => $order->load(['bookings.slots', 'bookings.court', 'bookings.holds' => fn ($q) => $q->whereNull('resolved_at')]),
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
            'booking' => $booking->load(['court', 'slots', 'holds' => fn ($q) => $q->whereNull('resolved_at')]),
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
            // A held booking has no current slots to reschedule "from" - it
            // resolves back to its pre-hold status instead of moving in
            // place. Same destination validation either way.
            $booking = $booking->status === 'on_hold'
                ? $this->bookings->resolveHold($booking, $court, $groups[0], Auth::user(), $data['reason'] ?? null)
                : $this->bookings->rescheduleBooking($booking, $court, $groups[0], Auth::user(), $data['reason'] ?? null);
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
     * "Hold" on a multi-session order lands here first: which of the
     * order's sessions is being held? Mirrors selectReschedule() above -
     * each session links on to holdForm() below for that one specific
     * booking, since a hold - like a reschedule - only ever touches one
     * session at a time.
     */
    public function selectHold(BookingOrder $order)
    {
        $this->authorizeCanManageBookings();

        return view('admin.bookings.hold-select', [
            'order' => $order->load(['bookings.slots', 'bookings.court', 'bookings.holds' => fn ($q) => $q->whereNull('resolved_at')]),
        ]);
    }

    /**
     * Pick which of a confirmed booking's hours to put on hold (e.g. rain).
     * No destination date/time is picked here - see BookingService::holdSlots().
     */
    public function holdForm(Booking $booking)
    {
        $this->authorizeCanManageBookings();

        abort_unless($this->isHoldable($booking), 422, 'This booking cannot be put on hold.');

        return view('admin.bookings.hold', [
            'booking' => $booking->load('court', 'slots'),
        ]);
    }

    public function hold(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate([
            'affected_court_slot_ids' => ['nullable', 'array'],
            'affected_court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Nothing selected means "hold the whole booking" - same convention
        // updateSplitReschedule() uses for its affected-hours picker.
        $slotIds = $data['affected_court_slot_ids'] ?? $booking->slots()->pluck('court_slots.id')->all();

        if (! empty($data['affected_court_slot_ids'])) {
            $groups = $this->bookings->groupContiguousSlotIds($slotIds);
            if (count($groups) !== 1) {
                return back()->withErrors(['affected_court_slot_ids' => 'Pick one contiguous block of hours to hold.'])->withInput();
            }
        }

        try {
            $booking = $this->bookings->holdSlots($booking, $slotIds, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException|SlotUnavailableException|\InvalidArgumentException $e) {
            return back()->withErrors(['affected_court_slot_ids' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.bookings.index')->with('status', "Booking {$booking->booking_code} put on hold.");
    }

    /**
     * Sidebar "Held Bookings" list - every booking currently on hold,
     * waiting on the admin to pick a new date/time via the same Reschedule
     * flow used everywhere else.
     */
    public function holds()
    {
        $this->authorizeCanManageBookings();

        $holds = BookingHold::whereNull('resolved_at')
            ->with(['booking.user:id,name,phone,email', 'fromCourt:id,name', 'heldBy:id,name'])
            ->latest('created_at')
            ->get();

        return view('admin.bookings.holds', ['holds' => $holds]);
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
     * Gives up on a held booking entirely instead of rescheduling it.
     * Deliberately does NOT reuse cancel() above - that method cascades to
     * BookingOrderService::cancel() whenever the target has an order, which
     * cancels every OTHER (pending_payment/confirmed) booking in the order
     * too and leaves the on_hold one itself untouched (on_hold isn't in the
     * set of statuses that cascade touches) - exactly backwards from what
     * "cancel this hold" means. A held booking always belongs to an order
     * (holding creates one to link the held piece and its sibling), so that
     * bug fired every time. This calls BookingService::cancel() directly on
     * just the one booking, bypassing the order-wide cascade.
     */
    public function cancelHold(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->bookings->cancel($booking, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('admin.bookings.holds.index')->with('status', "Hold on {$booking->booking_code} cancelled.");
    }

    /**
     * Removes just ONE date/session from a multi-date order (see the "Edit
     * order" page) - deliberately does NOT reuse cancel() above, same
     * reasoning as cancelHold(): cancel() cascades to
     * BookingOrderService::cancel() whenever the target has an order, which
     * would cancel every OTHER session in the order too instead of just this
     * one date. Calls BookingService::cancel() directly, bypassing the
     * order-wide cascade.
     */
    public function cancelSession(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $orderId = $booking->booking_order_id;

        try {
            $this->bookings->cancel($booking, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        if ($orderId) {
            return redirect()->route('admin.bookings.edit-order', $orderId)->with('status', "Removed {$booking->booking_code} from the order.");
        }

        return redirect()->route('admin.bookings.index')->with('status', "Booking {$booking->booking_code} cancelled.");
    }

    /**
     * Multi-session orders are represented by a single row wherever bookings
     * are listed - the other sessions in the order are shown nested inside
     * that row instead of as separate rows, so an order reads as the one
     * purchase it actually is. Prefers the earliest booking that ISN'T on
     * hold - otherwise, the moment its earliest session gets held (see
     * BookingService::holdSlots(), which always keeps the original id/code
     * on the held piece), the whole order - including sessions still
     * actively booked - would silently vanish from the default list the
     * instant the representative row's own status got filtered to on_hold.
     * Falls back to the plain earliest-id when every session in an order is
     * on hold (nothing else to prefer).
     */
    protected function representativeBookings(): \Illuminate\Database\Eloquent\Builder
    {
        return Booking::query()->where(function ($q) {
            $q->whereNull('booking_order_id')
                ->orWhereIn('id', function ($sub) {
                    $sub->selectRaw('MIN(id)')
                        ->from('bookings')
                        ->whereNotNull('booking_order_id')
                        ->where('status', '!=', 'on_hold')
                        ->groupBy('booking_order_id');
                })
                ->orWhereIn('id', function ($sub) {
                    $sub->selectRaw('MIN(id)')
                        ->from('bookings')
                        ->whereNotNull('booking_order_id')
                        ->groupBy('booking_order_id')
                        ->havingRaw("SUM(CASE WHEN status != 'on_hold' THEN 1 ELSE 0 END) = 0");
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
        // A held booking has zero slots by design, so the usual "last slot
        // date isn't in the past" check below doesn't apply to it - the
        // only thing that matters is whether it's actually still on hold.
        if ($booking->status === 'on_hold') {
            return $booking->holds()->whereNull('resolved_at')->exists();
        }

        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            return false;
        }

        $lastDate = $booking->slots->max('slot_date') ?? $booking->slots()->max('slot_date');

        if (! $lastDate || \Illuminate\Support\Carbon::parse($lastDate)->lt(today())) {
            return false;
        }

        return ! $booking->openPlayRoomCourt()->exists();
    }

    /**
     * Mirrors the guard in BookingService::holdSlots() so the "Hold" button
     * never shows up for something that would just error - only a currently
     * confirmed booking not tied to an active Open Play room can be held.
     */
    protected function isHoldable(Booking $booking): bool
    {
        return $booking->status === 'confirmed' && ! $booking->openPlayRoomCourt()->exists();
    }
}
