<?php

namespace App\Services;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingOrder;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingService
{
    /**
     * Create a booking across one or more contiguous slots for the given court.
     * Either a logged-in $customer or $guest contact details (name, phone, email) must be provided.
     *
     * @param  array<int>  $courtSlotIds
     * @param  array{name: string, phone: string, email?: string|null}|null  $guest
     */
    public function createBooking(?User $customer, Court $court, array $courtSlotIds, ?array $guest = null): Booking
    {
        if ($court->isUnderMaintenance()) {
            throw new SlotUnavailableException('This court is currently under maintenance.');
        }

        return DB::transaction(function () use ($customer, $court, $courtSlotIds, $guest) {
            $slots = CourtSlot::query()
                ->whereIn('id', $courtSlotIds)
                ->where('court_id', $court->id)
                ->lockForUpdate()
                ->orderBy('start_time')
                ->get();

            if ($slots->count() !== count($courtSlotIds)) {
                throw new SlotUnavailableException;
            }

            $this->assertContiguous($slots);

            if ($slots->contains(fn (CourtSlot $slot) => ! $slot->isAvailable())) {
                throw new SlotUnavailableException;
            }

            $booking = Booking::create([
                'booking_code' => $this->generateBookingCode(),
                'user_id' => $customer?->id,
                'guest_name' => $customer ? null : $guest['name'],
                'guest_phone' => $customer ? null : $guest['phone'],
                'guest_email' => $customer ? null : ($guest['email'] ?? null),
                'court_id' => $court->id,
                'status' => 'pending_payment',
                'total_price' => $slots->sum('price'),
                'receipt_token' => Str::random(40),
            ]);

            $booking->slots()->attach($slots->pluck('id'));

            CourtSlot::whereIn('id', $slots->pluck('id'))->update(['status' => 'booked']);

            $this->logStatus($booking, null, 'pending_payment', $customer, 'Booking created');

            return $booking->fresh(['slots', 'court']);
        });
    }

    /**
     * Front-desk / walk-in booking: admin books directly on the customer's
     * behalf and confirms it immediately, skipping the payment-review step
     * entirely (no GCash reference, no pending_payment stop-over).
     *
     * $rescheduledFrom links this to an earlier booking it replaces (e.g.
     * rained out, so the customer's already-paid slot moves to a new date
     * instead of the money going to waste) - no new payment is taken, and
     * the original is automatically cancelled so it doesn't keep sitting
     * there as if the customer is still expected to show up for a slot
     * that's not happening.
     *
     * @param  array<int>  $courtSlotIds
     * @param  array{name: string, phone: string, email?: string|null}|null  $guest
     */
    public function createConfirmedBooking(?User $customer, Court $court, array $courtSlotIds, ?array $guest, User $admin, ?Booking $rescheduledFrom = null): Booking
    {
        $booking = $this->createBooking($customer, $court, $courtSlotIds, $guest);

        if ($rescheduledFrom) {
            $booking->update(['rescheduled_from_id' => $rescheduledFrom->id]);
        }

        $booking = $this->approve($booking, $admin, 'Booked by admin, payment bypassed');

        if ($rescheduledFrom && in_array($rescheduledFrom->status, ['pending_payment', 'confirmed'], true)) {
            $this->cancel($rescheduledFrom, $admin, "Rescheduled to booking {$booking->booking_code}");
        }

        return $booking;
    }

    public function submitGcashReference(Booking $booking, string $reference, ?string $proofPath = null, ?int $paymentMethodId = null): Booking
    {
        if ($booking->status !== 'pending_payment') {
            throw new InvalidBookingTransitionException($booking->status, 'submit a payment reference for');
        }

        $booking->update([
            'payment_method_id' => $paymentMethodId ?? $booking->payment_method_id,
            'gcash_reference' => $reference,
            'payment_proof_path' => $proofPath ?? $booking->payment_proof_path,
            'gcash_submitted_at' => now(),
        ]);

        return $booking->fresh();
    }

    public function approve(Booking $booking, User $admin, string $note = 'Payment approved'): Booking
    {
        if ($booking->status !== 'pending_payment') {
            throw new InvalidBookingTransitionException($booking->status, 'approved');
        }

        $booking = DB::transaction(function () use ($booking, $admin, $note) {
            $lastSlot = $booking->slots()->orderBy('start_time')->get()->last();

            $booking->update([
                'status' => 'confirmed',
                'payment_reviewed_by' => $admin->id,
                'payment_reviewed_at' => now(),
                'checkin_token' => Str::random(40),
                'checkin_token_expires_at' => $lastSlot
                    ? $lastSlot->slot_date->clone()->setTimeFromTimeString($lastSlot->end_time)
                    : null,
            ]);

            $this->logStatus($booking, 'pending_payment', 'confirmed', $admin, $note);

            return $booking->fresh(['court', 'slots', 'user']);
        });

        $this->sendConfirmationEmail($booking);

        return $booking;
    }

    public function reject(Booking $booking, User $admin, ?string $reason = null): Booking
    {
        if ($booking->status !== 'pending_payment') {
            throw new InvalidBookingTransitionException($booking->status, 'rejected');
        }

        return DB::transaction(function () use ($booking, $admin, $reason) {
            CourtSlot::whereIn('id', $booking->slots()->pluck('court_slots.id'))
                ->update(['status' => 'available']);

            $booking->update([
                'status' => 'rejected',
                'payment_reviewed_by' => $admin->id,
                'payment_reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->logStatus($booking, 'pending_payment', 'rejected', $admin, $reason);

            return $booking->fresh();
        });
    }

    public function cancel(Booking $booking, ?User $actor, ?string $reason = null): Booking
    {
        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            throw new InvalidBookingTransitionException($booking->status, 'cancelled');
        }

        return DB::transaction(function () use ($booking, $actor, $reason) {
            $fromStatus = $booking->status;

            CourtSlot::whereIn('id', $booking->slots()->pluck('court_slots.id'))
                ->update(['status' => 'available']);

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->logStatus($booking, $fromStatus, 'cancelled', $actor, $reason);

            return $booking->fresh();
        });
    }

    /**
     * Auto-release bookings that have sat unpaid past the admin's configured
     * payment hold window - frees the slot back to available for everyone
     * else, same as a manual cancel, just triggered by the clock instead of
     * a person. Only bookings with no GCash reference yet are eligible - once
     * a customer has submitted one, it's awaiting admin review, not the
     * customer's payment, so it must not be auto-cancelled out from under them.
     *
     * $onlyDate scopes the sweep to bookings touching one slot_date, for cheap
     * on-read expiry from a specific date's availability endpoint - the full,
     * unscoped sweep still runs on a schedule (see bookings:expire-pending) as
     * the system-wide safety net.
     */
    public function expireStalePending(int $holdMinutes, ?string $onlyDate = null): int
    {
        $cutoff = now()->subMinutes($holdMinutes);

        $query = Booking::where('status', 'pending_payment')
            ->whereNull('gcash_reference')
            // Order-linked bookings are expired as a unit by
            // BookingOrderService::expireStalePending() instead, so the
            // order's own status/timestamps stay in sync with its children.
            ->whereNull('booking_order_id')
            ->where('created_at', '<=', $cutoff);

        if ($onlyDate) {
            $query->whereHas('slots', fn ($q) => $q->whereDate('slot_date', $onlyDate));
        }

        $stale = $query->get();

        foreach ($stale as $booking) {
            $this->cancel($booking, null, 'Payment window expired');
        }

        return $stale->count();
    }

    /**
     * Lazy, single-booking version of the same check - lets a page the
     * customer is actively looking at (their own status poll) reflect the
     * expiry the instant their countdown hits zero, without waiting for the
     * next scheduled sweep.
     */
    public function expireBookingIfStale(Booking $booking, int $holdMinutes): Booking
    {
        if ($booking->status !== 'pending_payment' || $booking->gcash_reference) {
            return $booking;
        }

        if ($booking->created_at->copy()->addMinutes($holdMinutes)->isFuture()) {
            return $booking;
        }

        return $this->cancel($booking, null, 'Payment window expired');
    }

    public function checkIn(Booking $booking, User $staff): Booking
    {
        if ($booking->status !== 'confirmed') {
            throw new InvalidBookingTransitionException($booking->status, 'checked in');
        }

        if ($booking->checked_in_at !== null) {
            throw new InvalidBookingTransitionException($booking->status, 'checked in again');
        }

        if ($booking->checkin_token_expires_at && $booking->checkin_token_expires_at->isPast()) {
            throw new InvalidBookingTransitionException($booking->status, 'checked in (token expired)');
        }

        return DB::transaction(function () use ($booking, $staff) {
            $booking->update([
                'status' => 'completed',
                'checked_in_at' => now(),
                'checked_in_by' => $staff->id,
            ]);

            $this->logStatus($booking, 'confirmed', 'completed', $staff, 'Checked in at front desk');

            return $booking->fresh();
        });
    }

    /**
     * Moves a booking to a new date/time (and optionally a new court) in
     * place - same id, same booking_code, same status throughout. Unlike
     * the old cancel-and-recreate approach, this doesn't touch payment
     * state or create a second row; it just swaps which CourtSlot(s) the
     * booking is attached to and records the move in rescheduleLogs() so
     * the old day/time isn't just silently forgotten.
     *
     * @param  array<int>  $newCourtSlotIds
     */
    public function rescheduleBooking(Booking $booking, Court $newCourt, array $newCourtSlotIds, ?User $admin, ?string $reason = null): Booking
    {
        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            throw new InvalidBookingTransitionException($booking->status, 'rescheduled');
        }

        $oldSlots = $booking->slots()->orderBy('start_time')->get();

        if ($oldSlots->isEmpty() || \Illuminate\Support\Carbon::parse($oldSlots->max('slot_date'))->lt(today())) {
            throw new InvalidBookingTransitionException($booking->status, 'rescheduled (nothing left to move)');
        }

        // An Open Play room snapshots session_date/start_time/court at
        // creation time and never re-reads the booking afterward, so
        // swapping this booking's slots under an active room would leave
        // the room silently pointing at the wrong day.
        if ($booking->openPlayRoomCourt()->exists()) {
            throw new InvalidBookingTransitionException($booking->status, 'rescheduled (linked to an Open Play room)');
        }

        return DB::transaction(function () use ($booking, $newCourt, $newCourtSlotIds, $admin, $reason, $oldSlots) {
            $newSlots = CourtSlot::query()
                ->whereIn('id', $newCourtSlotIds)
                ->where('court_id', $newCourt->id)
                ->lockForUpdate()
                ->orderBy('start_time')
                ->get();

            if ($newSlots->count() !== count($newCourtSlotIds)) {
                throw new SlotUnavailableException;
            }

            $this->assertContiguous($newSlots);

            if ($newSlots->contains(fn (CourtSlot $slot) => ! $slot->isAvailable())) {
                throw new SlotUnavailableException;
            }

            $oldFirst = $oldSlots->first();
            $oldLast = $oldSlots->last();
            $newFirst = $newSlots->first();
            $newLast = $newSlots->last();

            CourtSlot::whereIn('id', $oldSlots->pluck('id'))->update(['status' => 'available']);
            $booking->slots()->detach();

            $booking->slots()->attach($newSlots->pluck('id'));
            CourtSlot::whereIn('id', $newSlots->pluck('id'))->update(['status' => 'booked']);

            $booking->update([
                'court_id' => $newCourt->id,
                'total_price' => $newSlots->sum('price'),
            ]);

            $booking->rescheduleLogs()->create([
                'from_court_id' => $oldFirst->court_id,
                'from_slot_date' => $oldFirst->slot_date,
                'from_start_time' => $oldFirst->start_time,
                'from_end_time' => $oldLast->end_time,
                'to_court_id' => $newCourt->id,
                'to_slot_date' => $newFirst->slot_date,
                'to_start_time' => $newFirst->start_time,
                'to_end_time' => $newLast->end_time,
                'changed_by' => $admin?->id,
                'reason' => $reason,
            ]);

            return $booking->fresh(['slots', 'court', 'rescheduleLogs']);
        });
    }

    /**
     * Moves only PART of a booking's hours (e.g. rain hit just the 9-10am
     * hour of an 8-11am booking) to a new date/time/court, while the
     * untouched hours stay exactly as booked. The untouched slots split off
     * into new sibling Booking row(s) - own id/booking_code/receipt_token,
     * but copied customer/payment details - grouped under a shared
     * BookingOrder with $booking so they're still one purchase to the
     * admin/customer. $booking itself keeps its original id/booking_code/
     * checkin_token and, now holding only the affected slots, goes through
     * the same reschedule mechanics as rescheduleBooking() above.
     *
     * @param  array<int>  $affectedCourtSlotIds  subset of $booking's own slot ids being moved
     * @param  array<int>  $newCourtSlotIds  destination slots for just that subset
     * @return array{moved: Booking, remainder: \Illuminate\Support\Collection<int, Booking>}
     */
    public function splitAndReschedule(Booking $booking, array $affectedCourtSlotIds, Court $newCourt, array $newCourtSlotIds, ?User $admin, ?string $reason = null): array
    {
        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            throw new InvalidBookingTransitionException($booking->status, 'rescheduled');
        }

        $allSlots = $booking->slots()->orderBy('start_time')->get();

        if ($allSlots->isEmpty() || \Illuminate\Support\Carbon::parse($allSlots->max('slot_date'))->lt(today())) {
            throw new InvalidBookingTransitionException($booking->status, 'rescheduled (nothing left to move)');
        }

        if ($booking->openPlayRoomCourt()->exists()) {
            throw new InvalidBookingTransitionException($booking->status, 'rescheduled (linked to an Open Play room)');
        }

        $affectedSlots = $allSlots->whereIn('id', $affectedCourtSlotIds)->values();

        if ($affectedSlots->count() !== count($affectedCourtSlotIds)) {
            throw new SlotUnavailableException('One or more selected hours are not part of this booking.');
        }

        if ($affectedSlots->isEmpty()) {
            throw new \InvalidArgumentException('Select at least one hour to move.');
        }

        if ($affectedSlots->count() === $allSlots->count()) {
            throw new \InvalidArgumentException('All hours are affected — use a full reschedule instead of a split.');
        }

        // Removing one contiguous run from the middle/edge of another
        // contiguous run always leaves 0-2 contiguous remainder groups,
        // never more - safe to feed straight into groupContiguousSlotIds().
        $remainingIds = $allSlots->pluck('id')->diff($affectedSlots->pluck('id'))->values()->all();
        $remainderGroups = $this->groupContiguousSlotIds($remainingIds);

        return DB::transaction(function () use ($booking, $affectedSlots, $newCourt, $newCourtSlotIds, $admin, $reason, $remainderGroups) {
            $newSlots = CourtSlot::query()
                ->whereIn('id', $newCourtSlotIds)
                ->where('court_id', $newCourt->id)
                ->lockForUpdate()
                ->orderBy('start_time')
                ->get();

            if ($newSlots->count() !== count($newCourtSlotIds)) {
                throw new SlotUnavailableException;
            }

            $this->assertContiguous($newSlots);

            if ($newSlots->contains(fn (CourtSlot $slot) => ! $slot->isAvailable())) {
                throw new SlotUnavailableException;
            }

            // Every piece resulting from this split (the moved booking plus
            // any remainder siblings) needs to be one purchase, so they all
            // end up under one order - reuse the existing one if $booking
            // already has one, otherwise create it now.
            $order = $booking->bookingOrder;
            if (! $order) {
                $order = BookingOrder::create([
                    'receipt_token' => Str::random(40),
                    'user_id' => $booking->user_id,
                    'guest_name' => $booking->guest_name,
                    'guest_phone' => $booking->guest_phone,
                    'guest_email' => $booking->guest_email,
                    'total_price' => $booking->total_price,
                    'status' => $booking->status,
                    'payment_method_id' => $booking->payment_method_id,
                    'gcash_reference' => $booking->gcash_reference,
                    'payment_proof_path' => $booking->payment_proof_path,
                    'gcash_submitted_at' => $booking->gcash_submitted_at,
                    'payment_reviewed_by' => $booking->payment_reviewed_by,
                    'payment_reviewed_at' => $booking->payment_reviewed_at,
                ]);
                $booking->update(['booking_order_id' => $order->id]);
            }

            $remainderBookings = collect();

            foreach ($remainderGroups as $group) {
                $groupSlots = $booking->slots()->whereIn('court_slots.id', $group)->orderBy('start_time')->get();
                $lastSlot = $groupSlots->last();

                $sibling = Booking::create([
                    'booking_code' => $this->generateBookingCode(),
                    'user_id' => $booking->user_id,
                    'guest_name' => $booking->guest_name,
                    'guest_phone' => $booking->guest_phone,
                    'guest_email' => $booking->guest_email,
                    'court_id' => $booking->court_id,
                    'booking_order_id' => $order->id,
                    'status' => $booking->status,
                    'total_price' => $groupSlots->sum('price'),
                    'receipt_token' => Str::random(40),
                    'payment_method_id' => $booking->payment_method_id,
                    'gcash_reference' => $booking->gcash_reference,
                    'payment_proof_path' => $booking->payment_proof_path,
                    'gcash_submitted_at' => $booking->gcash_submitted_at,
                    'payment_reviewed_by' => $booking->payment_reviewed_by,
                    'payment_reviewed_at' => $booking->payment_reviewed_at,
                    'split_from_booking_id' => $booking->id,
                ]);

                // Slots aren't moving, just changing which booking owns
                // them - stays `booked` throughout, no re-lock needed.
                $booking->slots()->detach($group);
                $sibling->slots()->attach($group);

                if ($booking->status === 'confirmed') {
                    $sibling->update([
                        'checkin_token' => Str::random(40),
                        'checkin_token_expires_at' => $lastSlot
                            ? $lastSlot->slot_date->clone()->setTimeFromTimeString($lastSlot->end_time)
                            : null,
                    ]);
                }

                $this->logStatus($sibling, null, $sibling->status, $admin, "Split off from {$booking->booking_code} — kept at its original time");

                $remainderBookings->push($sibling->fresh(['slots', 'court']));
            }

            // $booking->slots() now holds only the affected slots (remainder
            // groups were detached above) - reschedule those, same shape as
            // rescheduleBooking() above.
            $oldFirst = $affectedSlots->first();
            $oldLast = $affectedSlots->last();
            $newFirst = $newSlots->first();
            $newLast = $newSlots->last();

            CourtSlot::whereIn('id', $affectedSlots->pluck('id'))->update(['status' => 'available']);
            $booking->slots()->detach();

            $booking->slots()->attach($newSlots->pluck('id'));
            CourtSlot::whereIn('id', $newSlots->pluck('id'))->update(['status' => 'booked']);

            $booking->update([
                'court_id' => $newCourt->id,
                'total_price' => $newSlots->sum('price'),
            ]);

            $booking->rescheduleLogs()->create([
                'from_court_id' => $oldFirst->court_id,
                'from_slot_date' => $oldFirst->slot_date,
                'from_start_time' => $oldFirst->start_time,
                'from_end_time' => $oldLast->end_time,
                'to_court_id' => $newCourt->id,
                'to_slot_date' => $newFirst->slot_date,
                'to_start_time' => $newFirst->start_time,
                'to_end_time' => $newLast->end_time,
                'changed_by' => $admin?->id,
                'reason' => $reason,
            ]);

            $order->update(['total_price' => $order->bookings()->sum('total_price')]);

            return [
                'moved' => $booking->fresh(['slots', 'court', 'rescheduleLogs']),
                'remainder' => $remainderBookings,
            ];
        });
    }

    /**
     * Splits a flat list of court slot ids into contiguous runs (same date,
     * back-to-back start/end times), in chronological order - so an
     * independently-selected group of slots (e.g. 1-2pm + 5-6pm, skipping
     * the gap) becomes one array per "session" instead of one array assumed
     * to be a single block. Each returned group is safe to pass straight
     * into createBooking()/createConfirmedBooking() - assertContiguous()
     * will always pass for it by construction.
     *
     * @param  array<int>  $courtSlotIds
     * @return array<int, array<int>>
     */
    public function groupContiguousSlotIds(array $courtSlotIds): array
    {
        $slots = CourtSlot::whereIn('id', $courtSlotIds)
            ->orderBy('slot_date')
            ->orderBy('start_time')
            ->get();

        $groups = [];
        $current = [];
        $previous = null;

        foreach ($slots as $slot) {
            $breaksRun = $previous
                && ($previous->slot_date->toDateString() !== $slot->slot_date->toDateString()
                    || $previous->end_time !== $slot->start_time);

            if ($breaksRun) {
                $groups[] = $current;
                $current = [];
            }

            $current[] = $slot->id;
            $previous = $slot;
        }

        if (! empty($current)) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CourtSlot>  $slots
     */
    protected function assertContiguous($slots): void
    {
        $slots->values()->each(function (CourtSlot $slot, int $index) use ($slots) {
            if ($index === 0) {
                return;
            }

            $previous = $slots->values()->get($index - 1);

            if (
                $previous->slot_date->toDateString() !== $slot->slot_date->toDateString()
                || $previous->end_time !== $slot->start_time
            ) {
                throw new NonContiguousSlotsException;
            }
        });
    }

    protected function generateBookingCode(): string
    {
        do {
            $code = 'PB-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }

    protected function logStatus(Booking $booking, ?string $from, string $to, ?User $actor, ?string $note): void
    {
        $booking->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'note' => $note,
        ]);
    }

    protected function sendConfirmationEmail(Booking $booking): void
    {
        $email = $booking->user->email ?? $booking->guest_email;

        if (! $email) {
            return;
        }

        Mail::to($email)->send(new BookingConfirmedMail($booking));
    }
}
