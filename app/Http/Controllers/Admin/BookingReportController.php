<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRescheduleLog;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\GameMatch;
use App\Models\OperatingHours;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingReportController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $courtId = $this->resolveCourtId($request);

        $bookings = Booking::query()
            ->with(['court:id,name', 'user:id,name'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($courtId, fn ($q) => $q->where('court_id', $courtId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.bookings', [
            'from' => $from,
            'to' => $to,
            'courtId' => $courtId,
            'courts' => Court::orderBy('name')->get(['id', 'name']),
            'bookings' => $bookings,
            'statusBreakdown' => $this->statusBreakdown($from, $to),
            'utilization' => $this->utilization($from, $to),
            'peakHours' => $this->peakHours($from, $to),
            'cancellationReasons' => $this->cancellationReasons($from, $to),
            'rebookImpact' => $this->rebookImpact($from, $to),
            'noShowCount' => Booking::where('status', 'no_show')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            'staffActivity' => $this->staffActivity($from, $to),
            'matchStats' => $this->matchStats($from, $to),
            'maintenance' => $this->maintenanceSnapshot(),
        ]);
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $courtId = $this->resolveCourtId($request);

        $rows = Booking::query()
            ->with(['court:id,name', 'user:id,name'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($courtId, fn ($q) => $q->where('court_id', $courtId))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Booking $b) => [
                $b->booking_code,
                $b->created_at->toDateTimeString(),
                $b->court->name,
                $b->contactName(),
                $b->status,
                number_format((float) $b->total_price, 2, '.', ''),
            ]);

        return $this->csvDownload(
            "bookings-{$from->toDateString()}-to-{$to->toDateString()}.csv",
            ['Booking Code', 'Created At', 'Court', 'Customer', 'Status', 'Total'],
            $rows
        );
    }

    /**
     * Printable day-sheet PDF: every court time-slot in range (not just
     * booked ones), so front-desk staff can see at a glance what's still
     * vacant vs who's occupying each slot. Capped to 31 days since this
     * renders one row per slot - a wide-open range times "all courts" would
     * otherwise produce a multi-thousand-row PDF that's unusable to print.
     */
    public function pdf(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $courtId = $this->resolveCourtId($request);

        abort_if($from->diffInDays($to) > 31, 422, 'Select a range of 31 days or less to print.');

        $courts = Court::query()
            ->when($courtId, fn ($q) => $q->where('id', $courtId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $slots = CourtSlot::query()
            ->whereBetween('slot_date', [$from->toDateString(), $to->toDateString()])
            ->when($courtId, fn ($q) => $q->where('court_id', $courtId))
            ->with(['bookings' => fn ($q) => $q->whereNotIn('bookings.status', ['cancelled', 'rejected'])->with('user:id,name,phone,email')])
            ->orderBy('start_time')
            ->get()
            ->groupBy([
                fn (CourtSlot $s) => $s->slot_date->toDateString(),
                fn (CourtSlot $s) => $s->court_id,
            ]);

        $brand = OperatingHours::current();
        // Prefer the logo uploaded in Settings; fall back to the static
        // public/logo.jpg so the print still has branding before an admin
        // ever uploads one through the settings page.
        $logoPath = $brand->logo_path ? storage_path('app/public/'.$brand->logo_path) : public_path('logo.png');

        $pdf = Pdf::loadView('admin.reports.bookings-schedule-pdf', [
            'from' => $from,
            'to' => $to,
            'courts' => $courts,
            'slots' => $slots,
            'brand' => $brand,
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ])->setPaper('legal', 'portrait');

        return $pdf->stream("court-schedule-{$from->toDateString()}-to-{$to->toDateString()}.pdf");
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

    protected function resolveCourtId(Request $request): ?int
    {
        return $request->filled('court_id') ? $request->integer('court_id') : null;
    }

    protected function statusBreakdown(Carbon $from, Carbon $to)
    {
        return Booking::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Booked court-slots ÷ total generated court-slots, per court, for the
     * range - a proxy for occupancy since every slot represents one fixed
     * block of court time regardless of who ends up in it.
     */
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

    /**
     * Demand by hour-of-day, counted off actually-paid bookings (not pending
     * holds) so it reflects real demand rather than in-flight checkouts.
     */
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

    /**
     * How many bookings this range were rescheduled (rain/reschedule), and
     * the revenue that carried forward with them. Two mechanisms feed this:
     * the legacy cancel-and-recreate flow (a new booking row, counted by its
     * own created_at) and the current in-place reschedule flow (same row,
     * logged via BookingRescheduleLog instead, since the booking's own
     * created_at never moves) - see Booking::rescheduledFrom()/rescheduleLogs().
     * Deduplicated so a booking rescheduled more than once in range is only
     * counted once, at its current total_price.
     */
    protected function rebookImpact(Carbon $from, Carbon $to): array
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $bookingIds = Booking::whereNotNull('rescheduled_from_id')
            ->whereBetween('created_at', $range)
            ->pluck('id')
            ->merge(
                BookingRescheduleLog::whereBetween('created_at', $range)->pluck('booking_id')
            )
            ->unique();

        $rebooked = Booking::whereIn('id', $bookingIds)->get(['total_price']);

        return ['count' => $rebooked->count(), 'total' => $rebooked->sum('total_price')];
    }

    /**
     * Courts under maintenance right now - a live snapshot, not a range
     * report. The schema only stores current maintenance state (status/
     * reason/until on the court itself); there's no history table logging
     * when maintenance started/ended, so historical "court-hours lost to
     * maintenance" for the selected date range genuinely can't be computed
     * from what's captured today. `updated_at` is shown as an approximate
     * "since" marker, but note it moves on any court edit, not only a
     * maintenance toggle.
     */
    protected function maintenanceSnapshot()
    {
        return Court::where('status', 'maintenance')
            ->orderBy('name')
            ->get(['name', 'maintenance_reason', 'maintenance_until', 'updated_at']);
    }

    /**
     * Approvals/rejections come from the booking_status_logs trail; check-ins
     * are tracked directly on the booking (checked_in_by/checked_in_at);
     * holds come from booking_holds.held_by - the newest staff action, added
     * alongside the "put on hold" feature.
     */
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

        $holds = DB::table('booking_holds')
            ->join('users', 'users.id', '=', 'booking_holds.held_by')
            ->whereBetween('booking_holds.created_at', $range)
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

        foreach ($holds as $h) {
            $byStaff[$h->name]['approvals'] ??= 0;
            $byStaff[$h->name]['rejections'] ??= 0;
            $byStaff[$h->name]['holds'] = $h->count;
        }

        foreach ($byStaff as $name => $data) {
            $byStaff[$name]['checkins'] ??= 0;
            $byStaff[$name]['holds'] ??= 0;
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
            'byScoringType' => $matches->groupBy('scoring_type')->map->count(),
            'byMatchType' => $matches->groupBy('match_type')->map->count(),
            'avgGamesPerMatch' => $matches->isNotEmpty() ? round($matches->avg('games_count'), 1) : 0,
        ];
    }
}
