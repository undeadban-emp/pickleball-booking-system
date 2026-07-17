<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidBookingTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function validateToken(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);

        $booking = Booking::where('checkin_token', $request->string('token'))
            ->with(['court', 'user:id,name', 'slots'])
            ->first();

        if (! $booking || $booking->status !== 'confirmed') {
            return response()->json(['message' => 'Invalid or expired check-in code.'], 404);
        }

        if ($booking->checkin_token_expires_at && $booking->checkin_token_expires_at->isPast()) {
            return response()->json(['message' => 'This check-in code has expired.'], 410);
        }

        return response()->json(['data' => $booking]);
    }

    public function confirm(Request $request, Booking $booking)
    {
        try {
            $booking = $this->bookings->checkIn($booking, $request->user());
        } catch (InvalidBookingTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $booking]);
    }
}
