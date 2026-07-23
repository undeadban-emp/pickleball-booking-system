<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $pending = Booking::with(['court', 'user:id,name'])
            ->where('status', 'pending_payment')
            ->latest()
            ->limit(8)
            ->get();

        // "Sales" = money actually confirmed received - payment_reviewed_at is
        // when an admin approved the reference (or, for a front-desk booking,
        // when it was created already-confirmed), not just when the customer
        // first submitted a booking that might still get rejected. See
        // Booking::scopeSales() for the rebook-exclusion rationale - the
        // ORIGINAL booking still counts on its original day even though it's
        // since been auto-cancelled as "rescheduled", otherwise the revenue
        // would vanish from the reports entirely instead of just moving.
        $salesQuery = fn ($from, $to) => Booking::sales()
            ->whereDate('payment_reviewed_at', '>=', $from)
            ->whereDate('payment_reviewed_at', '<=', $to)
            ->selectRaw('COALESCE(SUM(total_price), 0) as total, COUNT(*) as count')
            ->first();

        $todaySales = $salesQuery(today(), today());
        $yesterdaySales = $salesQuery(today()->subDay(), today()->subDay());

        // Optional admin-picked range (e.g. "how much did we make this month")
        // on top of the fixed today/yesterday cards above.
        $rangeFrom = $request->filled('from') ? $request->date('from') : null;
        $rangeTo = $request->filled('to') ? $request->date('to') : null;
        $rangeSales = ($rangeFrom && $rangeTo) ? $salesQuery($rangeFrom->min($rangeTo), $rangeFrom->max($rangeTo)) : null;

        return view('admin.dashboard', [
            'pending' => $pending,
            'pendingCount' => Booking::where('status', 'pending_payment')->count(),
            'confirmedToday' => Booking::where('status', 'confirmed')
                ->whereDate('payment_reviewed_at', today())
                ->count(),
            'checkedInToday' => Booking::where('status', 'completed')
                ->whereDate('checked_in_at', today())
                ->count(),
            'courtsUnderMaintenance' => Court::where('status', 'maintenance')->count(),
            'todaySalesTotal' => $todaySales->total,
            'todaySalesCount' => $todaySales->count,
            'yesterdaySalesTotal' => $yesterdaySales->total,
            'yesterdaySalesCount' => $yesterdaySales->count,
            'rangeFrom' => $rangeFrom,
            'rangeTo' => $rangeTo,
            'rangeSalesTotal' => $rangeSales->total ?? null,
            'rangeSalesCount' => $rangeSales->count ?? null,
        ]);
    }
}
