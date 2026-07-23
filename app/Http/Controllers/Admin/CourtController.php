<?php

namespace App\Http\Controllers\Admin;

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

        return view('admin.courts.index', ['courts' => $courts]);
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

        return back()->with('status', "{$court->name} added and slots generated for the next 14 days.");
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

        $priceChanged = bccomp((string) $court->default_price, (string) $data['default_price'], 2) !== 0;

        $court->update($data);

        if ($priceChanged) {
            $this->syncAvailableSlotPrices($court);
        }

        return back()->with('status', "{$court->name} updated.");
    }

    public function rates()
    {
        $courts = Court::orderBy('name')->get();

        return view('admin.settings.rates', ['courts' => $courts]);
    }

    public function updateRate(Request $request, Court $court)
    {
        $data = $request->validate([
            'default_price' => ['required', 'numeric', 'min:0'],
        ]);

        $court->update($data);

        $this->syncAvailableSlotPrices($court);

        return back()->with('status', "{$court->name}'s rate updated to ₱".number_format($data['default_price'], 2).'/hr.');
    }

    /**
     * Rate changes only take effect on slots that are still open — bookings
     * already made (and their locked-in price) are historical and untouched.
     */
    protected function syncAvailableSlotPrices(Court $court): void
    {
        CourtSlot::where('court_id', $court->id)
            ->where('status', 'available')
            ->update(['price' => $court->default_price]);
    }

    public function toggleActive(Court $court)
    {
        $court->update(['is_active' => ! $court->is_active]);

        return back()->with('status', $court->is_active
            ? "{$court->name} is now visible to customers."
            : "{$court->name} is now hidden from customers.");
    }

    public function toggleMaintenance(Request $request, Court $court)
    {
        if ($court->status === 'maintenance') {
            $court->update([
                'status' => 'active',
                'maintenance_reason' => null,
                'maintenance_until' => null,
            ]);

            return back()->with('status', "{$court->name} is back online.");
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

        return back()->with('status', "{$court->name} marked under maintenance.");
    }
}
