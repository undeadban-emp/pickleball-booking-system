<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    /**
     * Read-only: just proves the scanned/typed code belongs to a live,
     * confirmed booking and hands back a display-ready summary for staff to
     * eyeball. Nothing here mutates the booking - there's no separate
     * "confirm" step on the mobile app anymore, this endpoint alone is the
     * whole check-in. Shaped as a flat summary (not the raw Booking model)
     * so the app can render it directly without reformatting dates/prices
     * itself.
     */
    public function validateToken(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);

        $booking = Booking::where('checkin_token', $request->string('token'))
            ->with(['court:id,name', 'user:id,name,email', 'slots' => fn ($q) => $q->orderBy('start_time')])
            ->first();

        if (! $booking || $booking->status !== 'confirmed') {
            return response()->json(['message' => 'Invalid or expired check-in code.'], 404);
        }

        if ($booking->checkin_token_expires_at && $booking->checkin_token_expires_at->isPast()) {
            return response()->json(['message' => 'This check-in code has expired.'], 410);
        }

        return response()->json([
            'data' => [
                'reference' => $booking->booking_code,
                'court' => $booking->court->name,
                'schedule' => $booking->scheduleLines(),
                'customer' => $booking->contactName(),
                'email' => $booking->contactEmail(),
                'total' => $booking->total_price,
            ],
        ]);
    }
}
