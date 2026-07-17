<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;

class CourtController extends Controller
{
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

        $slots = $court->slots()
            ->where('slot_date', $date)
            ->where('status', 'available')
            ->orderBy('start_time')
            ->get(['id', 'start_time', 'end_time', 'price', 'status']);

        return response()->json(['data' => $slots]);
    }
}
