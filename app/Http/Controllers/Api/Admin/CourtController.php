<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\OperatingHours;
use App\Support\SlotGenerator;
use Illuminate\Http\Request;

class CourtController extends Controller
{
    public function index()
    {
        $courts = Court::withCount([
            'bookings as pending_bookings_count' => fn ($q) => $q->where('status', 'pending_payment'),
        ])->orderBy('name')->get();

        return response()->json(['data' => $courts]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:150'],
            'default_price' => ['required', 'numeric', 'min:0'],
        ]);

        $court = Court::create($data + ['status' => 'active', 'is_active' => true]);

        SlotGenerator::generate($court, OperatingHours::current());

        return response()->json(['data' => $court], 201);
    }

    public function update(Request $request, Court $court)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:150'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $court->update($data);

        return response()->json(['data' => $court]);
    }

    public function toggleActive(Court $court)
    {
        $court->update(['is_active' => ! $court->is_active]);

        return response()->json(['data' => $court]);
    }

    public function toggleMaintenance(Request $request, Court $court)
    {
        if ($court->status === 'maintenance') {
            $court->update([
                'status' => 'active',
                'maintenance_reason' => null,
                'maintenance_until' => null,
            ]);

            return response()->json(['data' => $court]);
        }

        $data = $request->validate([
            'maintenance_reason' => ['nullable', 'string', 'max:255'],
            'maintenance_until' => ['nullable', 'date'],
        ]);

        $court->update([
            'status' => 'maintenance',
            'maintenance_reason' => $data['maintenance_reason'] ?? null,
            'maintenance_until' => $data['maintenance_until'] ?? null,
        ]);

        return response()->json(['data' => $court]);
    }

    /**
     * Settings > "Court Rates" tab - same list as index() (courts already
     * carry default_price), kept as its own endpoint to mirror the web's
     * separate /admin/settings/rates page and give the app a stable route
     * name to point that screen at.
     */
    public function rates()
    {
        return response()->json(['data' => Court::orderBy('name')->get()]);
    }

    /**
     * Matches Admin\CourtController::updateRate() - unlike the general
     * court update() above, this also reprices already-generated but still
     * "available" slots so the new rate takes effect immediately instead of
     * only for slots generated after this change.
     */
    public function updateRate(Request $request, Court $court)
    {
        $data = $request->validate([
            'default_price' => ['required', 'numeric', 'min:0'],
        ]);

        $court->update($data);

        CourtSlot::where('court_id', $court->id)
            ->where('status', 'available')
            ->update(['price' => $court->default_price]);

        return response()->json(['data' => $court->fresh()]);
    }
}
