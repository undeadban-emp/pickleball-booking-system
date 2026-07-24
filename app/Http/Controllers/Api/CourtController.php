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
        $slots = Cache::remember("court-slots:{$court->id}:{$date}", 4, fn () => $court->slots()
            ->where('slot_date', $date)
            ->where('status', 'available')
            ->orderBy('start_time')
            ->get(['id', 'start_time', 'end_time', 'price', 'status']));

        return response()
            ->json(['data' => $slots])
            ->header('Cache-Control', 'no-store');
    }
}
