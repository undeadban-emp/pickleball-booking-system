<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;

class DashboardController extends Controller
{
    public function index()
    {
        $pending = Booking::with(['court', 'user:id,name'])
            ->where('status', 'pending_payment')
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'pending' => $pending,
            'pendingCount' => Booking::where('status', 'pending_payment')->count(),
            'confirmedToday' => Booking::where('status', 'confirmed')
                ->whereDate('payment_reviewed_at', today())
                ->count(),
            'checkedInToday' => Booking::where('status', 'completed')
                ->whereDate('checked_in_at', today())
                ->count(),
            'courtsUnderMaintenance' => Court::where('status', 'maintenance')->count(),
        ]);
    }
}
