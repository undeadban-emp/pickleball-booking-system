<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\OperatingHours;
use App\Services\BookingService;
use Illuminate\Http\Request;

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

        $data = $courts->map(function (Court $court) use ($slotsByCourtId) {
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
                ])->values(),
            ];
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
