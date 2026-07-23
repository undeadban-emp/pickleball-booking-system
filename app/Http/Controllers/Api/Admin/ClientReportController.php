<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClientReportController extends Controller
{
    /**
     * Same figures as the web "Client Reports" page
     * (Admin\ClientReportController).
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'top_customers' => $this->topCustomers($from, $to),
                'new_vs_returning' => $this->newVsReturning($from, $to),
                'guest_vs_registered' => $this->guestVsRegistered($from, $to),
            ],
        ]);
    }

    protected function resolveRange(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : today()->subDays(29);
        $to = $request->filled('to') ? Carbon::parse($request->string('to')) : today();

        return $from->lessThanOrEqualTo($to) ? [$from, $to] : [$to, $from];
    }

    protected function salesInRange(Carbon $from, Carbon $to)
    {
        return Booking::sales()->whereBetween('payment_reviewed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    protected function topCustomers(Carbon $from, Carbon $to, int $limit = 20)
    {
        return $this->salesInRange($from, $to)
            ->whereNotNull('user_id')
            ->join('users', 'users.id', '=', 'bookings.user_id')
            ->selectRaw('users.name, SUM(bookings.total_price) as total, COUNT(*) as count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    protected function newVsReturning(Carbon $from, Carbon $to): array
    {
        $customerIds = $this->salesInRange($from, $to)->whereNotNull('user_id')->distinct()->pluck('user_id');

        $new = 0;
        $returning = 0;

        foreach ($customerIds as $userId) {
            $hadEarlierSale = Booking::sales()
                ->where('user_id', $userId)
                ->where('payment_reviewed_at', '<', $from->copy()->startOfDay())
                ->exists();

            $hadEarlierSale ? $returning++ : $new++;
        }

        return ['new' => $new, 'returning' => $returning];
    }

    protected function guestVsRegistered(Carbon $from, Carbon $to): array
    {
        $bookings = $this->salesInRange($from, $to)->get(['user_id', 'total_price']);

        $guest = $bookings->whereNull('user_id');
        $registered = $bookings->whereNotNull('user_id');

        return [
            'guest' => ['count' => $guest->count(), 'total' => $guest->sum('total_price')],
            'registered' => ['count' => $registered->count(), 'total' => $registered->sum('total_price')],
        ];
    }
}
