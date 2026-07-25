<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\InvalidBookingTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectBookingRequest;
use App\Models\Booking;
use App\Models\BookingRescheduleLog;
use App\Models\BookingStatusLog;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request)
    {
        return Booking::query()
            ->with([
                'court:id,name',
                'user:id,name,phone,email',
                'slots' => fn ($q) => $q->orderBy('start_time'),
                'statusLogs' => fn ($q) => $q->orderByDesc('created_at'),
                'statusLogs.changedBy:id,name',
                'rescheduleLogs.changedBy:id,name',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('court_id'), fn ($q) => $q->where('court_id', $request->integer('court_id')))
            ->latest()
            ->paginate(20)
            ->through(fn (Booking $booking) => $this->present($booking));
    }

    /**
     * Single-booking detail for admin/staff - same shape as index(), but
     * fetched fresh (not restricted to the currently-loaded page) and not
     * subject to the customer-facing Api\BookingController::show() ownership
     * check, since admin/staff need to view any customer's booking.
     */
    public function show(Booking $booking)
    {
        $booking->load([
            'court:id,name',
            'user:id,name,phone,email',
            'slots' => fn ($q) => $q->orderBy('start_time'),
            'statusLogs' => fn ($q) => $q->orderByDesc('created_at'),
            'statusLogs.changedBy:id,name',
            'rescheduleLogs.changedBy:id,name',
        ]);

        return response()->json(['data' => $this->present($booking)]);
    }

    /**
     * Mirrors Api\Admin\ScheduleController::present() - same field shape
     * (payment/picture/history included) so the admin app's booking list,
     * day-schedule, and detail views are all consistent.
     */
    private function present(Booking $booking): array
    {
        $history = $booking->statusLogs
            ->map(fn (BookingStatusLog $log) => [
                'at' => $log->created_at,
                'label' => Booking::labelForStatus($log->to_status),
                'by' => $log->changedBy?->name,
            ])
            ->concat($booking->rescheduleLogs->map(fn (BookingRescheduleLog $log) => [
                'at' => $log->created_at,
                'label' => sprintf(
                    'Rescheduled from %s, %s to %s, %s',
                    $log->from_slot_date->format('M j'),
                    Carbon::parse($log->from_start_time)->format('g:i A').'–'.Carbon::parse($log->from_end_time)->format('g:i A'),
                    $log->to_slot_date->format('M j'),
                    Carbon::parse($log->to_start_time)->format('g:i A').'–'.Carbon::parse($log->to_end_time)->format('g:i A'),
                ),
                'by' => $log->changedBy?->name,
            ]))
            ->sortByDesc('at')
            ->values();

        return [
            ...$booking->toSummaryArray(),
            'is_guest' => $booking->isGuestBooking(),
            'payment' => $booking->gcash_reference || $booking->payment_proof_path ? [
                'reference' => $booking->gcash_reference,
                'submitted_at' => $booking->gcash_submitted_at?->format('M j, g:i A'),
                'proof_url' => $booking->paymentProofUrl(),
            ] : null,
            'note' => $booking->rejection_reason ?? $booking->cancellationSummary(),
            'history' => $history->map(fn (array $entry) => [
                'label' => $entry['label'],
                'at' => $entry['at']->format('M j, g:i A'),
                'by' => $entry['by'],
            ])->all(),
        ];
    }

    public function latest(Request $request)
    {
        $lastId = $request->integer('last_id', 0);

        $bookings = Booking::query()
            ->with(['court', 'user:id,name'])
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()
            ->json(['data' => $bookings])
            ->header('Cache-Control', 'no-store');
    }

    public function approve(Request $request, Booking $booking)
    {
        try {
            $booking = $this->bookings->approve($booking, $request->user());
        } catch (InvalidBookingTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $booking]);
    }

    public function reject(RejectBookingRequest $request, Booking $booking)
    {
        try {
            $booking = $this->bookings->reject($booking, $request->user(), $request->string('reason')->value() ?: null);
        } catch (InvalidBookingTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $booking]);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $booking = $this->bookings->cancel($booking, $request->user(), $request->string('reason')->value() ?: null);
        } catch (InvalidBookingTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $booking]);
    }
}
