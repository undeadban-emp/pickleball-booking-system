<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\OperatingHours;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RevenueReportController extends Controller
{
    use ExportsCsv;

    /**
     * The five buckets this whole report is built around. Every other
     * status ('no_show', legacy rows, etc.) is deliberately left out of the
     * cards/daily table - this report is meant to be read at a glance, not
     * an exhaustive dump of every status the `bookings` table can hold.
     */
    protected const BUCKETS = ['confirmed', 'pending', 'hold', 'rejected', 'cancelled'];

    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $courtId = $this->resolveCourtId($request);
        $bookingType = $this->resolveBookingType($request);
        $status = $this->resolveStatusFilter($request);

        $bookings = $this->loadBookings($from, $to, $courtId, $bookingType, $status);

        return view('admin.reports.revenue', [
            'from' => $from,
            'to' => $to,
            'courts' => Court::orderBy('name')->get(['id', 'name']),
            'courtId' => $courtId,
            'bookingType' => $bookingType,
            'status' => $status,
            'summary' => $this->summarize($bookings),
            'daily' => $this->dailyBreakdown($bookings),
            'byCourt' => $this->byCourt($bookings),
            'byBookingType' => $this->byBookingType($bookings),
        ]);
    }

    /**
     * Same figures as the on-screen report, laid out as a signable document
     * for an owner/accountant rather than the card/table layout used on
     * screen.
     */
    public function pdf(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $courtId = $this->resolveCourtId($request);
        $bookingType = $this->resolveBookingType($request);
        $status = $this->resolveStatusFilter($request);

        $bookings = $this->loadBookings($from, $to, $courtId, $bookingType, $status);

        $brand = OperatingHours::current();
        $logoPath = $brand->logo_path ? storage_path('app/public/'.$brand->logo_path) : public_path('logo.png');

        $pdf = Pdf::loadView('admin.reports.revenue-report-pdf', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->summarize($bookings),
            'daily' => $this->dailyBreakdown($bookings),
            'byCourt' => $this->byCourt($bookings),
            'byBookingType' => $this->byBookingType($bookings),
            'brand' => $brand,
            'logoPath' => is_file($logoPath) ? $logoPath : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("revenue-report-{$from->toDateString()}-to-{$to->toDateString()}.pdf");
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $courtId = $this->resolveCourtId($request);
        $bookingType = $this->resolveBookingType($request);

        $rows = Booking::sales()
            ->with(['court:id,name', 'user:id,name', 'paymentMethod:id,name'])
            ->whereBetween('payment_reviewed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($courtId, fn ($q) => $q->where('court_id', $courtId))
            ->when($bookingType === 'online', fn ($q) => $q->where('created_by_admin', false))
            ->when($bookingType === 'walk_in', fn ($q) => $q->where('created_by_admin', true))
            ->orderBy('payment_reviewed_at')
            ->get()
            ->map(fn (Booking $b) => [
                $b->booking_code,
                $b->payment_reviewed_at->toDateTimeString(),
                $b->court->name,
                $b->contactName(),
                $b->created_by_admin ? 'Walk-in' : 'Online',
                number_format((float) $b->total_price, 2, '.', ''),
            ]);

        return $this->csvDownload(
            "revenue-{$from->toDateString()}-to-{$to->toDateString()}.csv",
            ['Booking Code', 'Reviewed At', 'Court', 'Customer', 'Booking Type', 'Total'],
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

    protected function resolveCourtId(Request $request): ?int
    {
        return $request->filled('court_id') ? $request->integer('court_id') : null;
    }

    protected function resolveBookingType(Request $request): ?string
    {
        $value = $request->string('booking_type')->toString();

        return in_array($value, ['online', 'walk_in'], true) ? $value : null;
    }

    protected function resolveStatusFilter(Request $request): ?string
    {
        $value = $request->string('status')->toString();

        return in_array($value, ['confirmed', 'hold', 'rejected', 'cancelled'], true) ? $value : null;
    }

    /**
     * Which of the five report buckets a raw booking status belongs to.
     * 'completed' (checked-in) is folded into "confirmed" - it's still a
     * paid, honored booking. Anything else ('no_show', legacy rows) is
     * excluded from the report entirely.
     */
    protected function bucketFor(string $status): ?string
    {
        return match (true) {
            in_array($status, ['confirmed', 'completed'], true) => 'confirmed',
            $status === 'pending_payment' => 'pending',
            $status === 'on_hold' => 'hold',
            $status === 'rejected' => 'rejected',
            $status === 'cancelled' => 'cancelled',
            default => null,
        };
    }

    /**
     * Every booking created in range, filtered by court/booking type/status
     * and eager-loaded for downstream use - one query backs the cards, the
     * daily table, and the by-court/by-type breakdown, since they're all
     * just different cuts of the same filtered set.
     */
    protected function loadBookings(Carbon $from, Carbon $to, ?int $courtId, ?string $bookingType, ?string $status): Collection
    {
        return Booking::query()
            ->whereNull('rescheduled_from_id')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($courtId, fn ($q) => $q->where('court_id', $courtId))
            ->when($bookingType === 'online', fn ($q) => $q->where('created_by_admin', false))
            ->when($bookingType === 'walk_in', fn ($q) => $q->where('created_by_admin', true))
            ->when($status === 'confirmed', fn ($q) => $q->whereIn('status', ['confirmed', 'completed']))
            ->when($status === 'hold', fn ($q) => $q->where('status', 'on_hold'))
            ->when($status === 'rejected', fn ($q) => $q->where('status', 'rejected'))
            ->when($status === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->with(['court:id,name', 'holds' => fn ($q) => $q->whereNull('resolved_at')])
            ->get(['id', 'court_id', 'status', 'total_price', 'created_by_admin', 'created_at']);
    }

    /**
     * A booking's own total_price is zeroed out the moment it's put on
     * hold (see BookingService::holdSlots()), so its value has to be
     * reconstructed from the CourtSlot price(s) that originally covered
     * the range its active hold is sitting on.
     */
    protected function bookingValue(Booking $booking): float
    {
        if ($booking->status !== 'on_hold') {
            return (float) $booking->total_price;
        }

        $hold = $booking->holds->first();

        if (! $hold) {
            return 0.0;
        }

        return (float) CourtSlot::where('court_id', $hold->from_court_id)
            ->where('slot_date', $hold->from_slot_date)
            ->where('start_time', '>=', $hold->from_start_time)
            ->where('start_time', '<', $hold->from_end_time)
            ->sum('price');
    }

    protected function emptyBuckets(): array
    {
        return array_fill_keys(self::BUCKETS, ['count' => 0, 'total' => 0.0]);
    }

    protected function summarize(Collection $bookings): array
    {
        $buckets = $this->emptyBuckets();

        foreach ($bookings as $booking) {
            $key = $this->bucketFor($booking->status);

            if ($key === null) {
                continue;
            }

            $buckets[$key]['count']++;
            $buckets[$key]['total'] += $this->bookingValue($booking);
        }

        return $buckets;
    }

    protected function dailyBreakdown(Collection $bookings): Collection
    {
        return $bookings
            ->groupBy(fn (Booking $b) => $b->created_at->toDateString())
            ->map(function (Collection $dayBookings) {
                $buckets = $this->emptyBuckets();

                foreach ($dayBookings as $booking) {
                    $key = $this->bucketFor($booking->status);

                    if ($key === null) {
                        continue;
                    }

                    $buckets[$key]['count']++;
                    $buckets[$key]['total'] += $this->bookingValue($booking);
                }

                return $buckets;
            })
            ->sortKeys();
    }

    /**
     * Realized revenue only (confirmed/completed) - matches what "Revenue"
     * has always meant on this page, just now filterable by court/booking
     * type/status like the rest of the report.
     */
    protected function byCourt(Collection $bookings): Collection
    {
        return $bookings
            ->filter(fn (Booking $b) => $this->bucketFor($b->status) === 'confirmed')
            ->groupBy('court_id')
            ->map(fn (Collection $group) => [
                'label' => $group->first()->court->name ?? 'Unknown court',
                'count' => $group->count(),
                'total' => $group->sum(fn (Booking $b) => $this->bookingValue($b)),
            ])
            ->sortByDesc('total')
            ->values();
    }

    protected function byBookingType(Collection $bookings): Collection
    {
        return $bookings
            ->filter(fn (Booking $b) => $this->bucketFor($b->status) === 'confirmed')
            ->groupBy(fn (Booking $b) => $b->created_by_admin ? 'Walk-in' : 'Online')
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'count' => $group->count(),
                'total' => $group->sum(fn (Booking $b) => $this->bookingValue($b)),
            ])
            ->values();
    }
}
