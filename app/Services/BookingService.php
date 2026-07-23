<?php

namespace App\Services;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
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
     * $rebookedFrom links this to an earlier booking it replaces (e.g. rained
     * out, so the customer's already-paid slot moves to a new date instead of
     * the money going to waste) - no new payment is taken, and the original
     * is automatically cancelled so it doesn't keep sitting there as if the
     * customer is still expected to show up for a slot that's not happening.
     *
     * @param  array<int>  $courtSlotIds
     * @param  array{name: string, phone: string, email?: string|null}|null  $guest
     */
    public function createConfirmedBooking(?User $customer, Court $court, array $courtSlotIds, ?array $guest, User $admin, ?Booking $rebookedFrom = null): Booking
    {
        $booking = $this->createBooking($customer, $court, $courtSlotIds, $guest);

        if ($rebookedFrom) {
            $booking->update(['rebooked_from_id' => $rebookedFrom->id]);
        }

        $booking = $this->approve($booking, $admin, 'Booked by admin, payment bypassed');

        if ($rebookedFrom && in_array($rebookedFrom->status, ['pending_payment', 'confirmed'], true)) {
            $this->cancel($rebookedFrom, $admin, "Rescheduled — rebooked as {$booking->booking_code}");
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
