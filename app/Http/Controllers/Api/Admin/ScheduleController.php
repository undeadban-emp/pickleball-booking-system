<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    /**
     * Day-schedule view: every booking touching the given date, mirroring
     * Admin\BookingController::schedule() (the web "Day Schedule" page).
     * Shared by admin and staff, same as the web sidebar.
     */
    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->string('date')) : Carbon::today();

        $bookings = Booking::query()
            ->whereHas('slots', fn ($q) => $q->whereDate('slot_date', $date))
            // Cancelled bookings only belong on the day view if they were
            // cancelled because they got rebooked (rained out, rescheduled) -
            // any other cancellation never actually held the slot in a way
            // staff care about here. See the web equivalent for the same rule.
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhereHas('rebookedTo'))
            ->with([
                'court:id,name',
                'user:id,name,phone,email',
                'slots' => fn ($q) => $q->whereDate('slot_date', $date)->orderBy('start_time'),
                'statusLogs' => fn ($q) => $q->orderByDesc('created_at'),
                'statusLogs.changedBy:id,name',
                'rebookedFrom:id,booking_code',
                'rebookedFrom.slots' => fn ($q) => $q->orderBy('slot_date')->orderBy('start_time'),
            ])
            ->get()
            ->sortBy(fn (Booking $b) => $b->slots->first()?->start_time)
            ->values();

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'bookings' => $bookings->map(fn (Booking $booking) => $this->present($booking))->all(),
            ],
        ]);
    }

    private function present(Booking $booking): array
    {
        return [
            ...$booking->toSummaryArray(),
            'is_guest' => $booking->isGuestBooking(),
            'payment' => $booking->gcash_reference || $booking->payment_proof_path ? [
                'reference' => $booking->gcash_reference,
                'submitted_at' => $booking->gcash_submitted_at?->format('M j, g:i A'),
                'proof_url' => $booking->paymentProofUrl(),
            ] : null,
            'note' => $booking->rejection_reason ?? $booking->cancellationSummary(),
            'rebooked_from' => $booking->rebookedFrom ? [
                'reference' => $booking->rebookedFrom->booking_code,
                'schedule' => $booking->rebookedFrom->scheduleLines(),
            ] : null,
            'history' => $booking->statusLogs->map(fn (BookingStatusLog $log) => [
                'status' => $log->to_status,
                'label' => Booking::labelForStatus($log->to_status),
                'at' => $log->created_at->format('M j, g:i A'),
                'by' => $log->changedBy?->name,
            ])->all(),
        ];
    }
}
