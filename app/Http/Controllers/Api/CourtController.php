<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\OperatingHours;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CourtController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index()
    {
        return Court::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'location', 'default_price']);
    }

    public function slots(Request $request, Court $court)
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($court->isUnderMaintenance()) {
            return response()->json([
                'message' => 'This court is currently under maintenance.',
                'maintenance_reason' => $court->maintenance_reason,
                'maintenance_until' => $court->maintenance_until,
            ], 423);
        }

        $date = $request->date('date')->toDateString();

        // Release any slot held by an unpaid booking that's timed out, right
        // as this date is being viewed - so it shows available immediately
        // instead of waiting on the once-a-minute scheduled sweep.
        if ($holdMinutes = OperatingHours::current()->payment_hold_minutes) {
            $this->bookings->expireStalePending($holdMinutes, $date);
        }

        // Short-lived cache, same reasoning as AvailabilityController::index()
        // - the actual booking submission still re-checks + locks the slot in
        // BookingService::createBooking(), so a few seconds of staleness here
        // can't cause a double-booking, only a "sorry, just taken" retry.
        // Cached as a plain array, not an Eloquent Collection - the database
        // cache driver's serialize()/unserialize() round-trip doesn't play
        // well with model/Collection objects (see AvailabilityController).
        // Uses the 'file' store rather than the app's default 'database'
        // store, since the underlying query is already sub-millisecond and
        // writing through a MySQL table per cache miss cost more than it saved.
        $slots = Cache::store('file')->remember("court-slots:{$court->id}:{$date}", 4, function () use ($court, $date) {
            return $court->slots()
                ->where('slot_date', $date)
                ->with(['bookings' => fn ($q) => $q->whereIn('status', ['pending_payment', 'confirmed'])])
                ->orderBy('start_time')
                ->get(['id', 'court_id', 'start_time', 'end_time', 'price', 'status'])
                ->map(fn ($slot) => [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'price' => $slot->price,
                    // "available" is the only bookable state - blocked,
                    // pending, and booked are all shown but disabled so
                    // customers can see the day is busy instead of the slot
                    // just vanishing with no explanation.
                    'status' => $this->displayStatus($slot),
                ])
                ->toArray();
        });

        return response()
            ->json(['data' => $slots])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Dates within the given month where every generated slot for this
     * court is already blocked or held by a pending/confirmed booking -
     * lets the calendar mark whole days red instead of the customer
     * having to click into each one to discover it's fully booked.
     */
    public function fullyBookedDates(Request $request, Court $court)
    {
        $request->validate([
            'month' => ['required_without_all:from,to', 'date_format:Y-m'],
            'from' => ['required_without:month', 'date_format:Y-m-d'],
            'to' => ['required_without:month', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        if ($request->filled('month')) {
            $rangeStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $request->string('month').'-01')->startOfMonth();
            $rangeEnd = $rangeStart->copy()->endOfMonth();
        } else {
            $rangeStart = \Illuminate\Support\Carbon::parse($request->string('from'));
            $rangeEnd = \Illuminate\Support\Carbon::parse($request->string('to'));
        }

        $dates = Cache::store('file')->remember(
            "court-fully-booked:{$court->id}:{$rangeStart->toDateString()}:{$rangeEnd->toDateString()}",
            30,
            function () use ($court, $rangeStart, $rangeEnd) {
                return $court->slots()
                    ->whereBetween('slot_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                    ->with(['bookings' => fn ($q) => $q->whereIn('status', ['pending_payment', 'confirmed'])])
                    ->get(['id', 'court_id', 'slot_date', 'status'])
                    ->groupBy(fn ($slot) => $slot->slot_date->toDateString())
                    ->filter(fn ($slots) => $slots->every(fn ($slot) => $slot->status === 'blocked' || $slot->bookings->isNotEmpty()))
                    ->keys()
                    ->values()
                    ->all();
            }
        );

        return response()
            ->json(['data' => $dates])
            ->header('Cache-Control', 'no-store');
    }

    protected function displayStatus($slot): string
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
