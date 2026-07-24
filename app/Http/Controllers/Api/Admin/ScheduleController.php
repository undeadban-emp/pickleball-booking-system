<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRescheduleLog;
use App\Models\BookingStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    /**
     * Day-schedule view: every booking touching the given date, mirroring
     * Admin\BookingController::schedule() (the web "Day Schedule" page).
     * Shared by admin and staff, same as the web sidebar. Reschedules now
     * update the same booking row in place (BookingRescheduleLog) instead
     * of creating a replacement booking, so "moved" bookings are surfaced
     * via `rescheduled_away`, not a rebooked/rescheduled-from pointer.
     */
    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->string('date')) : Carbon::today();

        $bookings = Booking::query()
            ->whereHas('slots', fn ($q) => $q->whereDate('slot_date', $date))
            // Cancelled bookings only belong on the day view if they were
            // cancelled because they got rescheduled (rained out, moved to a
            // new date) - any other cancellation never actually held the
            // slot in a way staff care about here. Legacy: rescheduledTo
            // only matches bookings moved via the old cancel-and-recreate
            // mechanism, before reschedules started updating the same row
            // in place - see the web equivalent for the same rule.
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhereHas('rescheduledTo'))
            ->with([
                'court:id,name',
                'user:id,name,phone,email',
                'slots' => fn ($q) => $q->whereDate('slot_date', $date)->orderBy('start_time'),
                'statusLogs' => fn ($q) => $q->orderByDesc('created_at'),
                'statusLogs.changedBy:id,name',
                'rescheduleLogs.changedBy:id,name',
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

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'bookings' => $bookings->map(fn (Booking $booking) => $this->present($booking))->all(),
                'rescheduled_away' => $rescheduledAway->map(fn (BookingRescheduleLog $log) => [
                    'reference' => $log->booking->booking_code,
                    'customer' => $log->booking->contactName(),
                    'moved_to' => sprintf(
                        '%s, %s to %s',
                        $log->to_slot_date->format('M j, Y'),
                        Carbon::parse($log->to_start_time)->format('g:i A'),
                        Carbon::parse($log->to_end_time)->format('g:i A'),
                    ),
                ])->all(),
            ],
        ]);
    }

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
}
