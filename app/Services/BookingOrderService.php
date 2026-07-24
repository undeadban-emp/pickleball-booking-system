<?php

namespace App\Services;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingOrder;
use App\Models\Court;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "One payment, multiple sessions" checkout: wraps BookingService so an
 * independently-selected, possibly non-contiguous group of court slots
 * becomes one Booking per contiguous run, unified under a single
 * BookingOrder for payment/approval - without changing any of
 * BookingService's own single-booking behavior (still the code path used
 * for the common single-session case, admin walk-ins, and reschedules).
 */
class BookingOrderService
{
    public function __construct(protected BookingService $bookings) {}

    /**
     * @param  array<int>  $courtSlotIds
     * @param  array{name: string, phone: string, email?: string|null}|null  $guest
     */
    public function checkout(?User $customer, Court $court, array $courtSlotIds, ?array $guest = null): Booking|BookingOrder
    {
        $groups = $this->bookings->groupContiguousSlotIds($courtSlotIds);

        if (count($groups) <= 1) {
            // Common case: a single contiguous selection behaves exactly as
            // it always has - no order wrapper at all.
            return $this->bookings->createBooking($customer, $court, $courtSlotIds, $guest);
        }

        return DB::transaction(function () use ($customer, $court, $groups, $guest) {
            $order = BookingOrder::create([
                'receipt_token' => Str::random(40),
                'user_id' => $customer?->id,
                'guest_name' => $customer ? null : $guest['name'],
                'guest_phone' => $customer ? null : $guest['phone'],
                'guest_email' => $customer ? null : ($guest['email'] ?? null),
                'total_price' => 0,
                'status' => 'pending_payment',
            ]);

            $total = 0;
            $createdAny = false;

            foreach ($groups as $group) {
                try {
                    $booking = $this->bookings->createBooking($customer, $court, $group, $guest);
                } catch (SlotUnavailableException) {
                    // Someone grabbed this particular session between the
                    // page loading and submit - skip it, keep the rest.
                    continue;
                }

                $booking->update(['booking_order_id' => $order->id]);
                $total += (float) $booking->total_price;
                $createdAny = true;
            }

            if (! $createdAny) {
                throw new SlotUnavailableException('None of the selected times are available anymore.');
            }

            $order->update(['total_price' => $total]);

            return $order->fresh(['bookings.slots', 'bookings.court']);
        });
    }

    public function submitGcashReference(BookingOrder $order, ?string $reference, ?string $proofPath = null, ?int $paymentMethodId = null): BookingOrder
    {
        if ($order->status !== 'pending_payment') {
            throw new InvalidBookingTransitionException($order->status, 'submit a payment reference for');
        }

        $attributes = [
            'payment_method_id' => $paymentMethodId ?? $order->payment_method_id,
            'gcash_reference' => $reference ?? $order->gcash_reference,
            'payment_proof_path' => $proofPath ?? $order->payment_proof_path,
            'gcash_submitted_at' => now(),
        ];

        $order->update($attributes);

        // Mirror onto every child booking too, so the admin's existing
        // per-booking screens show the same reference/proof even though the
        // approve/reject decision itself happens at the order level below.
        $order->bookings()->update($attributes);

        return $order->fresh();
    }

    public function approve(BookingOrder $order, User $admin): BookingOrder
    {
        if ($order->status !== 'pending_payment') {
            throw new InvalidBookingTransitionException($order->status, 'approved');
        }

        return DB::transaction(function () use ($order, $admin) {
            foreach ($order->bookings as $booking) {
                if ($booking->status === 'pending_payment') {
                    $this->bookings->approve($booking, $admin, 'Payment approved (order)');
                }
            }

            // One check-in code for the whole order, so a multi-session
            // customer can show a single QR at the gate instead of one per
            // session - valid until the last session of the order ends.
            $latestExpiry = $order->fresh('bookings')->bookings->max('checkin_token_expires_at');

            $order->update([
                'status' => 'confirmed',
                'payment_reviewed_by' => $admin->id,
                'payment_reviewed_at' => now(),
                'checkin_token' => Str::random(40),
                'checkin_token_expires_at' => $latestExpiry,
            ]);

            return $order->fresh('bookings');
        });
    }

    public function reject(BookingOrder $order, User $admin, ?string $reason = null): BookingOrder
    {
        if ($order->status !== 'pending_payment') {
            throw new InvalidBookingTransitionException($order->status, 'rejected');
        }

        return DB::transaction(function () use ($order, $admin, $reason) {
            foreach ($order->bookings as $booking) {
                if ($booking->status === 'pending_payment') {
                    $this->bookings->reject($booking, $admin, $reason);
                }
            }

            $order->update([
                'status' => 'rejected',
                'payment_reviewed_by' => $admin->id,
                'payment_reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $order->fresh('bookings');
        });
    }

    public function cancel(BookingOrder $order, ?User $actor, ?string $reason = null): BookingOrder
    {
        if (! in_array($order->status, ['pending_payment', 'confirmed'], true)) {
            throw new InvalidBookingTransitionException($order->status, 'cancelled');
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            foreach ($order->bookings as $booking) {
                if (in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
                    $this->bookings->cancel($booking, $actor, $reason);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $order->fresh('bookings');
        });
    }

    /**
     * Same idea as BookingService::expireStalePending(), scoped to orders -
     * called from the same scheduled sweep.
     */
    public function expireStalePending(int $holdMinutes): int
    {
        $cutoff = now()->subMinutes($holdMinutes);

        $stale = BookingOrder::where('status', 'pending_payment')
            ->whereNull('gcash_reference')
            ->whereNull('payment_proof_path')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($stale as $order) {
            $this->cancel($order, null, 'Payment window expired');
        }

        return $stale->count();
    }

    /**
     * Orders confirmed before the single-order-QR feature shipped won't
     * have a checkin_token yet - this fills it in on demand, the same way
     * approve() does, instead of requiring a data migration.
     */
    public function backfillCheckinToken(BookingOrder $order): BookingOrder
    {
        $latestExpiry = $order->loadMissing('bookings')->bookings->max('checkin_token_expires_at');

        $order->update([
            'checkin_token' => Str::random(40),
            'checkin_token_expires_at' => $latestExpiry,
        ]);

        return $order->fresh();
    }

    public function expireOrderIfStale(BookingOrder $order, int $holdMinutes): BookingOrder
    {
        if ($order->status !== 'pending_payment' || $order->hasSubmittedPayment()) {
            return $order;
        }

        if ($order->created_at->copy()->addMinutes($holdMinutes)->isFuture()) {
            return $order;
        }

        return $this->cancel($order, null, 'Payment window expired');
    }
}
