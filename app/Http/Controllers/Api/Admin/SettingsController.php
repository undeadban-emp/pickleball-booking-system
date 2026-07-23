<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function show()
    {
        return response()->json(['data' => OperatingHours::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // "Closes at" may be earlier in clock-time than "Morning starts" (e.g. 2am close,
            // 6am open) - that means the venue stays open past midnight, not an error.
            'close_time' => ['required', 'date_format:H:i'],
            'slot_length_minutes' => ['required', 'integer', 'in:30,60,90,120'],
            'booking_widget_style' => ['required', 'in:grid,by_court'],
            'period_morning_start' => ['required', 'date_format:H:i'],
            'period_afternoon_start' => ['required', 'date_format:H:i'],
            'period_evening_start' => ['required', 'date_format:H:i'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'logo_height' => ['required', 'integer', 'min:16', 'max:120'],
            'show_brand_text' => ['nullable', 'boolean'],
            'brand_text' => ['required', 'string', 'max:60'],
        ]);

        $settings = OperatingHours::current();

        $updates = [
            'open_time' => $data['period_morning_start'].':00',
            'close_time' => $data['close_time'].':00',
            'slot_length_minutes' => $data['slot_length_minutes'],
            'booking_widget_style' => $data['booking_widget_style'],
            'period_morning_start' => $data['period_morning_start'].':00',
            'period_afternoon_start' => $data['period_afternoon_start'].':00',
            'period_evening_start' => $data['period_evening_start'].':00',
            'logo_height' => $data['logo_height'],
            'show_brand_text' => $request->boolean('show_brand_text'),
            'brand_text' => $data['brand_text'],
        ];

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $updates['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        $settings->update($updates);

        return response()->json(['data' => $settings->fresh()]);
    }

    public function removeLogo()
    {
        $settings = OperatingHours::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        return response()->json(['data' => $settings->fresh()]);
    }
}
