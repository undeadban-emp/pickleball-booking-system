<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\OperatingHours;
use App\Support\SlotGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', ['settings' => OperatingHours::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'booking_widget_style' => ['required', 'in:grid,by_court'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'logo_height' => ['required', 'integer', 'min:16', 'max:120'],
            'show_brand_text' => ['nullable', 'boolean'],
            'brand_text' => ['required', 'string', 'max:60'],
            'payment_hold_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $settings = OperatingHours::current();

        $updates = [
            'booking_widget_style' => $data['booking_widget_style'],
            'logo_height' => $data['logo_height'],
            'show_brand_text' => $request->boolean('show_brand_text'),
            'brand_text' => $data['brand_text'],
            'payment_hold_minutes' => $data['payment_hold_minutes'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $updates['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        $settings->update($updates);

        return back()->with('status', 'Settings updated.');
    }

    public function removeLogo()
    {
        $settings = OperatingHours::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        return back()->with('status', 'Logo removed. The default mark will be used instead.');
    }

    public function editHours()
    {
        return view('admin.settings.hours', ['settings' => OperatingHours::current()]);
    }

    public function updateHours(Request $request)
    {
        // A period's end may be earlier in clock-time than its start (e.g. Evening
        // 5pm to 2am) - that means it runs past midnight, not an error.
        $data = $request->validate([
            'slot_length_minutes' => ['required', 'integer', 'in:30,60,90,120'],
            'period_morning_start' => ['required', 'date_format:H:i'],
            'period_morning_end' => ['required', 'date_format:H:i'],
            'period_afternoon_start' => ['required', 'date_format:H:i'],
            'period_afternoon_end' => ['required', 'date_format:H:i'],
            'period_evening_start' => ['required', 'date_format:H:i'],
            'period_evening_end' => ['required', 'date_format:H:i'],
        ]);

        $settings = OperatingHours::current();

        $settings->update([
            // Open/close still drive slot generation - derived from Morning's
            // start and Evening's end, the first and last boundaries.
            'open_time' => $data['period_morning_start'].':00',
            'close_time' => $data['period_evening_end'].':00',
            'slot_length_minutes' => $data['slot_length_minutes'],
            'period_morning_start' => $data['period_morning_start'].':00',
            'period_morning_end' => $data['period_morning_end'].':00',
            'period_afternoon_start' => $data['period_afternoon_start'].':00',
            'period_afternoon_end' => $data['period_afternoon_end'].':00',
            'period_evening_start' => $data['period_evening_start'].':00',
            'period_evening_end' => $data['period_evening_end'].':00',
        ]);

        $settings = $settings->fresh();

        // Keep already-generated days in sync with the new hours immediately,
        // instead of only affecting slots generated from this point forward.
        SlotGenerator::pruneOutOfWindow($settings);
        Court::all()->each(fn (Court $court) => SlotGenerator::generate($court, $settings));

        return back()->with('status', 'Hours updated. The booking calendar reflects these hours now.');
    }

    public function editLocation()
    {
        return view('admin.settings.location', ['settings' => OperatingHours::current()]);
    }

    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'map_location' => ['nullable', 'string', 'max:150'],
            'map_lat' => ['required', 'numeric', 'between:-90,90'],
            'map_lng' => ['required', 'numeric', 'between:-180,180'],
            'map_style' => ['required', 'in:standard,light,dark,satellite,terrain'],
        ]);

        OperatingHours::current()->update($data);

        return back()->with('status', 'Location updated. The homepage map now points here.');
    }
}
