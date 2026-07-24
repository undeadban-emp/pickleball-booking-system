<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingReportController extends Controller
{
    /**
     * Same figures as the web "Booking Reports" page
     * (Admin\BookingReportController) - PDF/CSV export isn't relevant on
     * mobile, so only the on-screen stats are ported here.
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $courtId = $request->filled('court_id') ? $request->integer('court_id') : null;

        $bookings = Booking::query()
            ->with(['court:id,name', 'user:id,name,phone,email', 'slots' => fn ($q) => $q->orderBy('start_time')])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($courtId, fn ($q) => $q->where('court_id', $courtId))
            ->latest()
            ->paginate(20)
            ->through(fn (Booking $b) => $b->toSummaryArray());

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'status_breakdown' => $this->statusBreakdown($from, $to),
                'utilization' => $this->utilization($from, $to),
                'peak_hours' => $this->peakHours($from, $to),
                'cancellation_reasons' => $this->cancellationReasons($from, $to),
                'rebook_impact' => $this->rebookImpact($from, $to),
                'no_show_count' => Booking::where('status', 'no_show')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'staff_activity' => $this->staffActivity($from, $to),
                'match_stats' => $this->matchStats($from, $to),
                'maintenance' => $this->maintenanceSnapshot(),
                'bookings' => $bookings,
            ],
        ]);
    }

    protected function resolveRange(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : today()->subDays(29);
        $to = $request->filled('to') ? Carbon::parse($request->string('to')) : today();

        return $from->lessThanOrEqualTo($to) ? [$from, $to] : [$to, $from];
    }

    protected function statusBreakdown(Carbon $from, Carbon $to)
    {
        return Booking::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
    }

    protected function utilization(Carbon $from, Carbon $to)
    {
        return Court::query()
            ->withCount([
                'slots as total_slots' => fn ($q) => $q->whereBetween('slot_date', [$from->toDateString(), $to->toDateString()]),
                'slots as booked_slots' => fn ($q) => $q->whereBetween('slot_date', [$from->toDateString(), $to->toDateString()])->where('status', 'booked'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Court $court) => [
                'name' => $court->name,
                'total' => $court->total_slots,
                'booked' => $court->booked_slots,
                'rate' => $court->total_slots > 0 ? round(($court->booked_slots / $court->total_slots) * 100) : 0,
            ]);
    }

    protected function peakHours(Carbon $from, Carbon $to)
    {
        return DB::table('booking_slots')
            ->join('bookings', 'bookings.id', '=', 'booking_slots.booking_id')
            ->join('court_slots', 'court_slots.id', '=', 'booking_slots.court_slot_id')
            ->whereIn('bookings.status', ['confirmed', 'completed'])
            ->whereBetween('court_slots.slot_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('HOUR(court_slots.start_time) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    protected function cancellationReasons(Carbon $from, Carbon $to)
    {
        return Booking::where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("COALESCE(cancellation_reason, 'Not specified') as reason, COUNT(*) as count")
            ->groupBy('reason')
            ->orderByDesc('count')
            ->get();
    }

    protected function rebookImpact(Carbon $from, Carbon $to): array
    {
        $rebooked = Booking::whereNotNull('rescheduled_from_id')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['total_price']);

        return ['count' => $rebooked->count(), 'total' => $rebooked->sum('total_price')];
    }

    protected function maintenanceSnapshot()
    {
        return Court::where('status', 'maintenance')
            ->orderBy('name')
            ->get(['name', 'maintenance_reason', 'maintenance_until', 'updated_at']);
    }

    protected function staffActivity(Carbon $from, Carbon $to)
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $logs = DB::table('booking_status_logs')
            ->join('users', 'users.id', '=', 'booking_status_logs.changed_by')
            ->whereIn('booking_status_logs.to_status', ['confirmed', 'rejected'])
            ->whereBetween('booking_status_logs.created_at', $range)
            ->selectRaw('users.name, booking_status_logs.to_status, COUNT(*) as count')
            ->groupBy('users.name', 'booking_status_logs.to_status')
            ->get();

        $checkins = DB::table('bookings')
            ->join('users', 'users.id', '=', 'bookings.checked_in_by')
            ->whereBetween('bookings.checked_in_at', $range)
            ->selectRaw('users.name, COUNT(*) as count')
            ->groupBy('users.name')
            ->get();

        $byStaff = [];

        foreach ($logs as $log) {
            $byStaff[$log->name]['approvals'] ??= 0;
            $byStaff[$log->name]['rejections'] ??= 0;
            $byStaff[$log->name][$log->to_status === 'confirmed' ? 'approvals' : 'rejections'] = $log->count;
        }

        foreach ($checkins as $c) {
            $byStaff[$c->name]['approvals'] ??= 0;
            $byStaff[$c->name]['rejections'] ??= 0;
            $byStaff[$c->name]['checkins'] = $c->count;
        }

        foreach ($byStaff as $name => $data) {
            $byStaff[$name]['checkins'] ??= 0;
        }

        return $byStaff;
    }

    protected function matchStats(Carbon $from, Carbon $to): array
    {
        $matches = GameMatch::query()
            ->withCount('games')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        return [
            'total' => $matches->count(),
            'by_scoring_type' => $matches->groupBy('scoring_type')->map->count(),
            'by_match_type' => $matches->groupBy('match_type')->map->count(),
            'avg_games_per_match' => $matches->isNotEmpty() ? round($matches->avg('games_count'), 1) : 0,
        ];
    }
}
