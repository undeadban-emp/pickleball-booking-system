<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\CourtSlot;
use App\Models\OperatingHours;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RevenueReportController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        return view('admin.reports.revenue', [
            'from' => $from,
            'to' => $to,
            'trend' => $this->trend($from, $to),
            'byCourt' => $this->byCourt($from, $to),
            'byPaymentMethod' => $this->byPaymentMethod($from, $to),
            'bySource' => $this->bySource($from, $to),
            'pendingAging' => $this->pendingAging(),
            'holdRevenue' => $this->holdRevenue(),
            'lost' => $this->lostRevenue($from, $to),
        ]);
    }

    /**
     * Formal finance-statement-style PDF - the same figures as the on-screen
     * dashboard, laid out as a signable document (summary totals up top,
     * tabular breakdowns, sign-off lines at the bottom) rather than the
     * bar-chart cards used on screen, since those don't print well and this
     * is meant to be handed to an owner/accountant, not clicked through.
     */
    public function pdf(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $trend = $this->trend($from, $to);
        $lost = $this->lostRevenue($from, $to);

        $totalRevenue = $trend->sum('total');
        $totalBookings = (int) $trend->sum('count');

        $brand = OperatingHours::current();
        $logoPath = $brand->logo_path ? storage_path('app/public/'.$brand->logo_path) : public_path('logo.png');

        $pdf = Pdf::loadView('admin.reports.revenue-report-pdf', [
            'from' => $from,
            'to' => $to,
            'trend' => $trend,
            'byCourt' => $this->byCourt($from, $to),
            'byPaymentMethod' => $this->byPaymentMethod($from, $to),
            'bySource' => $this->bySource($from, $to),
            'pendingAging' => $this->pendingAging(),
            'holdRevenue' => $this->holdRevenue(),
            'lost' => $lost,
            'totalRevenue' => $totalRevenue,
            'totalBookings' => $totalBookings,
            'avgPerBooking' => $totalBookings > 0 ? $totalRevenue / $totalBookings : 0,
            'brand' => $brand,
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("revenue-report-{$from->toDateString()}-to-{$to->toDateString()}.pdf");
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $rows = Booking::sales()
            ->with(['court:id,name', 'user:id,name', 'paymentMethod:id,name'])
            ->whereBetween('payment_reviewed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('payment_reviewed_at')
            ->get()
            ->map(fn (Booking $b) => [
                $b->booking_code,
                $b->payment_reviewed_at->toDateTimeString(),
                $b->court->name,
                $b->contactName(),
                $b->paymentMethod->name ?? 'Cash / Unspecified',
                number_format((float) $b->total_price, 2, '.', ''),
            ]);

        return $this->csvDownload(
            "revenue-{$from->toDateString()}-to-{$to->toDateString()}.csv",
            ['Booking Code', 'Reviewed At', 'Court', 'Customer', 'Payment Method', 'Total'],
            $rows
        );
    }

    protected function resolveRange(Request $request): array
    {
        $from = $this->parseDate($request->string('from')) ?? today()->subDays(29);
        $to = $this->parseDate($request->string('to')) ?? today();

        return $from->lessThanOrEqualTo($to) ? [$from, $to] : [$to, $from];
    }

    /**
     * Malformed "from"/"to" query params (bad manual URL edits, stray
     * copy-paste, etc.) used to crash this whole page with a raw Carbon
     * parse exception - fall back to the default range instead.
     */
    protected function parseDate($value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function salesInRange(Carbon $from, Carbon $to)
    {
        return Booking::sales()->whereBetween('payment_reviewed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    protected function trend(Carbon $from, Carbon $to)
    {
        return $this->salesInRange($from, $to)
            ->selectRaw('DATE(payment_reviewed_at) as d, SUM(total_price) as total, COUNT(*) as count')
            ->groupBy('d')
            ->orderBy('d')
            ->get();
    }

    protected function byCourt(Carbon $from, Carbon $to)
    {
        return $this->salesInRange($from, $to)
            ->join('courts', 'courts.id', '=', 'bookings.court_id')
            ->selectRaw('courts.name as court_name, SUM(bookings.total_price) as total, COUNT(*) as count')
            ->groupBy('courts.name')
            ->orderByDesc('total')
            ->get();
    }

    protected function byPaymentMethod(Carbon $from, Carbon $to)
    {
        return $this->salesInRange($from, $to)
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'bookings.payment_method_id')
            ->selectRaw("COALESCE(payment_methods.name, 'Cash / Unspecified') as method_name, SUM(bookings.total_price) as total, COUNT(*) as count")
            ->groupBy('method_name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * "Front Desk" vs "Online" - derived from who logged the booking's very
     * first status entry (see BookingService::createBooking/createConfirmedBooking),
     * since there's no explicit source column on bookings itself.
     */
    protected function bySource(Carbon $from, Carbon $to): array
    {
        $bookings = $this->salesInRange($from, $to)
            ->with('statusLogs.changedBy:id,role')
            ->get(['bookings.id', 'bookings.total_price']);

        $groups = $bookings->groupBy(function (Booking $booking) {
            $firstLog = $booking->statusLogs->sortBy('created_at')->first();
            $actor = $firstLog?->changedBy;

            return ($actor && $actor->role !== 'customer') ? 'Front Desk' : 'Online';
        });

        return $groups->map(fn ($g, $label) => [
            'label' => $label,
            'count' => $g->count(),
            'total' => $g->sum('total_price'),
        ])->values()->all();
    }

    /**
     * Snapshot of right-now, not the selected range - "aging" means how long
     * a booking has sat waiting, which only makes sense as of today.
     */
    protected function pendingAging()
    {
        $pending = Booking::where('status', 'pending_payment')->get(['id', 'total_price', 'created_at']);

        $buckets = [
            '0-1 days' => fn ($days) => $days <= 1,
            '2-3 days' => fn ($days) => $days >= 2 && $days <= 3,
            '4-7 days' => fn ($days) => $days >= 4 && $days <= 7,
            '8+ days' => fn ($days) => $days >= 8,
        ];

        return collect($buckets)->map(function ($matches) use ($pending) {
            $matching = $pending->filter(fn (Booking $b) => $matches($b->created_at->diffInDays(now())));

            return ['count' => $matching->count(), 'total' => $matching->sum('total_price')];
        });
    }

    /**
     * Bookings currently sitting on_hold, right now - a live snapshot like
     * pendingAging(), not a date-range figure, since "on hold" is a state a
     * booking is in today, not something that happened within a window.
     * BookingHold remembers exactly which court/date/time-range it held but
     * not what it was worth (holdSlots() zeroes the booking's own
     * total_price the moment it goes on hold), so the value is reconstructed
     * by summing the CourtSlot price(s) that originally covered that range.
     */
    protected function holdRevenue(): array
    {
        $activeHolds = BookingHold::whereNull('resolved_at')->get();

        $withValue = $activeHolds->map(function (BookingHold $hold) {
            $value = CourtSlot::where('court_id', $hold->from_court_id)
                ->where('slot_date', $hold->from_slot_date)
                ->where('start_time', '>=', $hold->from_start_time)
                ->where('start_time', '<', $hold->from_end_time)
                ->sum('price');

            return ['value' => $value, 'reason' => $hold->reason ?: 'Not specified'];
        });

        $byReason = $withValue->groupBy('reason')
            ->map(fn ($g) => ['count' => $g->count(), 'total' => $g->sum('value')])
            ->sortByDesc('total');

        return [
            'count' => $withValue->count(),
            'total' => $withValue->sum('value'),
            'byReason' => $byReason,
        ];
    }

    protected function lostRevenue(Carbon $from, Carbon $to): array
    {
        $rejected = Booking::where('status', 'rejected')
            ->whereBetween('payment_reviewed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['total_price', 'rejection_reason']);

        $cancelled = Booking::where('status', 'cancelled')
            ->whereDoesntHave('rescheduledTo')
            ->whereBetween('cancelled_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['total_price', 'cancellation_reason']);

        $byReason = $rejected->map(fn ($b) => ['reason' => $b->rejection_reason ?? 'Not specified', 'total' => $b->total_price])
            ->merge($cancelled->map(fn ($b) => ['reason' => $b->cancellation_reason ?? 'Not specified', 'total' => $b->total_price]))
            ->groupBy('reason')
            ->map(fn ($g) => ['count' => $g->count(), 'total' => $g->sum('total')])
            ->sortByDesc('total');

        return [
            'rejectedTotal' => $rejected->sum('total_price'),
            'rejectedCount' => $rejected->count(),
            'cancelledTotal' => $cancelled->sum('total_price'),
            'cancelledCount' => $cancelled->count(),
            'byReason' => $byReason,
        ];
    }
}
