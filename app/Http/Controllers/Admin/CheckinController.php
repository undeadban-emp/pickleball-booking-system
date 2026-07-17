<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckinController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request)
    {
        $booking = null;

        if ($request->filled('token')) {
            $booking = Booking::where('checkin_token', $request->string('token'))
                ->with(['court', 'slots'])
                ->first();
        }

        return view('admin.checkin.index', [
            'booking' => $booking,
            'searched' => $request->filled('token'),
        ]);
    }

    public function confirm(Request $request, Booking $booking)
    {
        try {
            $this->bookings->checkIn($booking, Auth::user());
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('admin.checkin.index')
            ->with('status', "{$booking->booking_code} checked in. Have a good game.");
    }
}
