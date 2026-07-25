<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\OperatingHours;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AvailabilityController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    /**
     * Grid of every active court's slots for a given date, with a display
     * status suitable for a booked/available/pending color-coded view.
     */
    public function index(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = $request->string('date')->toString();

        // Release any slot held by an unpaid booking that's timed out, right
        // as this date is being viewed - so it shows available immediately
        // instead of waiting on the once-a-minute scheduled sweep.
        if ($holdMinutes = OperatingHours::current()->payment_hold_minutes) {
            $this->bookings->expireStalePending($holdMinutes, $date);
        }

        // Short-lived cache so a burst of simultaneous viewers (or a scripted
        // poller ignoring the rate limit's spirit) doesn't turn into one full
        // courts+slots query per request - a few seconds of staleness is an
        // acceptable tradeoff, the same staleness window a slot already had
        // against the once-a-minute expiry sweep before this cache existed.
        // Explicitly the 'file' store (not the app's default 'database'
        // store) - the underlying queries here are already sub-millisecond,
        // so writing through the database cache table (an extra MySQL
        // INSERT ... ON DUPLICATE KEY UPDATE per miss) cost more than it
        // saved and could even serialize against a near-simultaneous
        // background poll hitting the same cache row.
        $data = Cache::store('file')->remember("availability:{$date}", 4, function () use ($date) {
            $courts = Court::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'maintenance_reason']);

            $slotsByCourtId = CourtSlot::query()
                ->whereIn('court_id', $courts->pluck('id'))
                ->where('slot_date', $date)
                ->with(['bookings' => fn ($q) => $q->whereIn('status', ['pending_payment', 'confirmed'])])
                ->orderBy('start_time')
                ->get()
                ->groupBy('court_id');

            // Plain array, not a Collection - the database cache driver
            // serializes via PHP's serialize()/unserialize(), which chokes
            // on nested Collection objects (comes back as an unusable
            // __PHP_Incomplete_Class_Name on the next cache hit). A plain
            // array round-trips cleanly.
            return $courts->map(function (Court $court) use ($slotsByCourtId) {
                $slots = $slotsByCourtId->get($court->id, collect());

                return [
                    'id' => $court->id,
                    'name' => $court->name,
                    'is_under_maintenance' => $court->status === 'maintenance',
                    'maintenance_reason' => $court->maintenance_reason,
                    'slots' => $slots->map(fn (CourtSlot $slot) => [
                        'id' => $slot->id,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'price' => $slot->price,
                        'status' => $this->displayStatus($slot),
                    ])->values()->all(),
                ];
            })->values()->all();
        });

        return response()
            ->json(['date' => $date, 'courts' => $data])
            ->header('Cache-Control', 'no-store');
    }

    protected function displayStatus(CourtSlot $slot): string
    {
        if ($slot->status === 'blocked') {
            return 'blocked';
        }

        $activeBooking = $slot->bookings->first();

        return match ($activeBooking?->status) {
            'pending_payment' => 'pending',
            'confirmed' => 'booked',
            default => 'available',
        };
    }
}
