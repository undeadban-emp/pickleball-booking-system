<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\SubmitGcashReferenceRequest;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function mine(Request $request)
    {
        return $request->user()
            ->bookings()
            ->with(['court:id,name', 'user:id,name,phone,email', 'slots' => fn ($q) => $q->orderBy('start_time')])
            ->latest()
            ->paginate(15)
            ->through(fn (Booking $booking) => $booking->toSummaryArray());
    }

    public function store(StoreBookingRequest $request)
    {
        $court = Court::findOrFail($request->integer('court_id'));

        try {
            $booking = $this->bookings->createBooking(
                $request->user(),
                $court,
                $request->input('court_slot_ids')
            );
        } catch (NonContiguousSlotsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (SlotUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $booking,
            'payment_info' => [
                'gcash_number' => config('booking.gcash_number'),
                'gcash_qr_url' => config('booking.gcash_qr_url'),
            ],
        ], 201);
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        $booking->load([
            'court:id,name',
            'user:id,name,phone,email',
            'slots' => fn ($q) => $q->orderBy('start_time'),
            'statusLogs' => fn ($q) => $q->orderByDesc('created_at'),
            'statusLogs.changedBy:id,name',
        ]);

        return response()->json([
            'data' => [
                ...$booking->toSummaryArray(),
                'payment' => $this->paymentSummary($booking),
                'history' => $booking->statusLogs->map(fn (BookingStatusLog $log) => [
                    'status' => $log->to_status,
                    'label' => Booking::labelForStatus($log->to_status),
                    'at' => $log->created_at->format('M j, g:i A'),
                    'by' => $this->historyActor($log, $booking),
                ])->all(),
            ],
        ]);
    }

    public function submitGcashReference(SubmitGcashReferenceRequest $request, Booking $booking)
    {
        $booking = $this->bookings->submitGcashReference($booking, $request->string('gcash_reference'));
        $booking->load(['court:id,name', 'user:id,name,phone,email', 'slots' => fn ($q) => $q->orderBy('start_time')]);

        return response()->json([
            'data' => [
                ...$booking->toSummaryArray(),
                'payment' => $this->paymentSummary($booking),
            ],
        ]);
    }

    public function checkinQr(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_unless($booking->status === 'confirmed', 404);

        return response()->json([
            'checkin_token' => $booking->checkin_token,
            'checkin_url' => url("/checkin/{$booking->checkin_token}"),
            'expires_at' => $booking->checkin_token_expires_at,
        ]);
    }

    /**
     * Null when the booking never went through the GCash flow at all (e.g.
     * a front-desk booking, payment bypassed) - lets the app skip the
     * Payment section entirely instead of rendering an empty one.
     */
    private function paymentSummary(Booking $booking): ?array
    {
        if (! $booking->gcash_reference && ! $booking->payment_proof_path) {
            return null;
        }

        return [
            'reference' => $booking->gcash_reference,
            'submitted_at' => $booking->gcash_submitted_at?->format('M j, g:i A'),
            'proof_url' => $booking->paymentProofUrl(),
        ];
    }

    /**
     * Who actioned a history entry. `changed_by` is null for the very first
     * "pending_payment" entry when it was a guest checkout, and for the
     * scheduled sweep that auto-cancels stale unpaid bookings - neither has
     * a User row to point at.
     */
    private function historyActor(BookingStatusLog $log, Booking $booking): string
    {
        if ($log->changedBy) {
            return $log->changedBy->name;
        }

        if ($log->from_status === null && $log->to_status === 'pending_payment') {
            return $booking->contactName();
        }

        return 'System (auto-expired)';
    }
}
