<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\OperatingHours;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClientReportController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        return view('admin.reports.clients', [
            'from' => $from,
            'to' => $to,
            'topCustomers' => $this->topCustomers($from, $to),
            'newVsReturning' => $this->newVsReturning($from, $to),
            'guestVsRegistered' => $this->guestVsRegistered($from, $to),
        ]);
    }

    /**
     * Same figures as the on-screen dashboard, laid out as a printable
     * document. Top-customers is capped at 100 rows (vs. the CSV export's
     * unlimited) so the print stays a reasonable handful of pages.
     */
    public function pdf(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $brand = OperatingHours::current();
        $logoPath = $brand->logo_path ? storage_path('app/public/'.$brand->logo_path) : public_path('logo.png');

        $pdf = Pdf::loadView('admin.reports.client-report-pdf', [
            'from' => $from,
            'to' => $to,
            'topCustomers' => $this->topCustomers($from, $to, limit: 100),
            'newVsReturning' => $this->newVsReturning($from, $to),
            'guestVsRegistered' => $this->guestVsRegistered($from, $to),
            'brand' => $brand,
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("client-report-{$from->toDateString()}-to-{$to->toDateString()}.pdf");
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $rows = $this->topCustomers($from, $to, limit: PHP_INT_MAX)
            ->map(fn ($row) => [$row->name, $row->count, number_format((float) $row->total, 2, '.', '')]);

        return $this->csvDownload(
            "clients-{$from->toDateString()}-to-{$to->toDateString()}.csv",
            ['Customer', 'Bookings', 'Total Spend'],
            $rows
        );
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

    /**
     * Registered customers only - guests have no stable identity to roll
     * up spend across separate bookings by (see guestVsRegistered() for the
     * guest side of the split).
     */
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

    /**
     * "New" = this is the customer's first-ever sale, and it happened to
     * land inside the selected range. "Returning" = they had at least one
     * sale before the range started. Guests are excluded (no stable identity).
     */
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
