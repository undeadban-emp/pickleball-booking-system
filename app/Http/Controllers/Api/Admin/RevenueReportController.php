<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RevenueReportController extends Controller
{
    /**
     * Same figures as the web "Revenue & Finance Reports" page
     * (Admin\RevenueReportController).
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $trend = $this->trend($from, $to);

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'total_revenue' => $trend->sum('total'),
                'total_bookings' => (int) $trend->sum('count'),
                'trend' => $trend,
                'by_court' => $this->byCourt($from, $to),
                'by_payment_method' => $this->byPaymentMethod($from, $to),
                'by_source' => $this->bySource($from, $to),
                'pending_aging' => $this->pendingAging(),
                'lost' => $this->lostRevenue($from, $to),
            ],
        ]);
    }

    protected function resolveRange(Request $request): array
    {
        $from = $this->parseDate($request->string('from')) ?? today()->subDays(29);
        $to = $this->parseDate($request->string('to')) ?? today();

        return $from->lessThanOrEqualTo($to) ? [$from, $to] : [$to, $from];
    }

    /**
     * Malformed "from"/"to" query params used to crash this endpoint with a
     * raw Carbon parse exception - fall back to the default range instead.
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
            'rejected_total' => $rejected->sum('total_price'),
            'rejected_count' => $rejected->count(),
            'cancelled_total' => $cancelled->sum('total_price'),
            'cancelled_count' => $cancelled->count(),
            'by_reason' => $byReason,
        ];
    }
}
