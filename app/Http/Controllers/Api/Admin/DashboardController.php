<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Mirrors the web admin dashboard (Admin\DashboardController) figure
     * for figure - same "sales" definition (see Booking::scopeSales()),
     * same today/yesterday cards, same optional date-range card, same
     * "needs your review" queue. Kept as its own admin-only screen (not
     * merged into the staff-visible Bookings/Check-in/Day Schedule set)
     * since the analytics here aren't something a front-desk staff device
     * needs.
     */
    public function index(Request $request)
    {
        $pending = Booking::with(['court:id,name', 'user:id,name,phone,email', 'slots' => fn ($q) => $q->orderBy('start_time')])
            ->where('status', 'pending_payment')
            ->latest()
            ->limit(8)
            ->get();

        $salesQuery = fn (Carbon $from, Carbon $to) => Booking::sales()
            ->whereDate('payment_reviewed_at', '>=', $from)
            ->whereDate('payment_reviewed_at', '<=', $to)
            ->selectRaw('COALESCE(SUM(total_price), 0) as total, COUNT(*) as count')
            ->first();

        $todaySales = $salesQuery(today(), today());
        $yesterdaySales = $salesQuery(today()->subDay(), today()->subDay());

        $rangeFrom = $request->filled('from') ? Carbon::parse($request->string('from')) : null;
        $rangeTo = $request->filled('to') ? Carbon::parse($request->string('to')) : null;
        $rangeSales = ($rangeFrom && $rangeTo) ? $salesQuery($rangeFrom->min($rangeTo), $rangeFrom->max($rangeTo)) : null;

        return response()->json([
            'data' => [
                'pending_count' => Booking::where('status', 'pending_payment')->count(),
                'confirmed_today' => Booking::where('status', 'confirmed')->whereDate('payment_reviewed_at', today())->count(),
                'checked_in_today' => Booking::where('status', 'completed')->whereDate('checked_in_at', today())->count(),
                'courts_under_maintenance' => Court::where('status', 'maintenance')->count(),
                'sales_today' => ['total' => $todaySales->total, 'count' => $todaySales->count],
                'sales_yesterday' => ['total' => $yesterdaySales->total, 'count' => $yesterdaySales->count],
                'sales_range' => $rangeSales ? [
                    'from' => $rangeFrom->toDateString(),
                    'to' => $rangeTo->toDateString(),
                    'total' => $rangeSales->total,
                    'count' => $rangeSales->count,
                ] : null,
                'needs_review' => $pending->map(fn (Booking $booking) => [
                    ...$booking->toSummaryArray(),
                    'gcash_reference' => $booking->gcash_reference,
                    'proof_url' => $booking->paymentProofUrl(),
                ])->all(),
            ],
        ]);
    }
}
