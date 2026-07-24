<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        $bookings = $this->representativeBookings($user)
            ->with([
                'court', 'slots', 'bookingOrder' => fn ($q) => $q->withCount('bookings'), 'bookingOrder.bookings.slots',
                'holds' => fn ($q) => $q->whereNull('resolved_at'),
            ])
            ->latest()
            ->paginate(10);

        $displayStatuses = $bookings->getCollection()->mapWithKeys(fn (Booking $b) => [$b->id => $this->effectiveStatus($b)]);

        return view('bookings.index', ['bookings' => $bookings, 'displayStatuses' => $displayStatuses]);
    }

    public function poll(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter();

        $bookings = $ids->isNotEmpty()
            ? $user->bookings()
                ->whereIn('id', $ids)
                ->with(['slots:id,slot_date', 'bookingOrder' => fn ($q) => $q->withCount('bookings'), 'bookingOrder.bookings.slots:id,slot_date'])
                ->get(['id', 'status', 'gcash_reference', 'payment_proof_path', 'booking_order_id'])
            : collect();

        return response()
            ->json([
                'pending_count' => $this->representativeBookings($user)->where('status', 'pending_payment')->count(),
                'statuses' => $bookings->mapWithKeys(fn (Booking $b) => [$b->id => $this->effectiveStatus($b)]),
                'has_reference' => $bookings->mapWithKeys(fn (Booking $b) => [$b->id => $b->hasSubmittedPayment()]),
            ])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * A confirmed booking whose court time has already passed reads as
     * "Completed" to the customer even if front-desk never scanned a
     * check-in QR (staff don't always remember to, especially for a
     * no-drama booking that just came and went) - but only once EVERY
     * session is done, not just some of them, so a multi-session order
     * with one date still upcoming still shows as "Confirmed" as a whole.
     * This is purely a display label - the underlying `status` column is
     * untouched, so admin/check-in logic keeps working off the real value.
     */
    protected function effectiveStatus(Booking $booking): string
    {
        if ($booking->status !== 'confirmed') {
            return $booking->status;
        }

        $isOrder = $booking->bookingOrder && $booking->bookingOrder->bookings_count > 1;

        $lastSlotDate = $isOrder
            ? $booking->bookingOrder->bookings->flatMap->slots->max('slot_date')
            : $booking->slots->max('slot_date');

        if ($lastSlotDate && \Illuminate\Support\Carbon::parse($lastSlotDate)->lt(today())) {
            return 'completed';
        }

        return 'confirmed';
    }

    /**
     * A multi-session order is one purchase, so it counts/appears as a
     * single booking here instead of one per session - same collapsing the
     * admin bookings list does. Prefers the earliest session that ISN'T on
     * hold - otherwise, the moment an order's earliest session gets held
     * (see BookingService::holdSlots(), which always keeps the original id
     * on the held piece), this row would represent the whole order with the
     * held session's status/blank schedule even though the rest of the
     * order might still be fully active. Falls back to the plain
     * earliest-id when every session in an order is on hold.
     */
    protected function representativeBookings(User $user): HasMany
    {
        return $user->bookings()->where(function ($q) {
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
}
