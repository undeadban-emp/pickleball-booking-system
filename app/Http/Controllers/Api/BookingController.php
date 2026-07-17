<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\SubmitGcashReferenceRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function mine(Request $request)
    {
        return $request->user()
            ->bookings()
            ->with(['court', 'slots'])
            ->latest()
            ->paginate(15);
    }

    public function store(StoreBookingRequest $request)
    {
        $court = Court::findOrFail($request->integer('court_id'));

        try {
            $booking = $this->bookings->createBooking(
                $request->user(),
                $court,
                $request->input('court_slot_ids')
            );
        } catch (NonContiguousSlotsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (SlotUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $booking,
            'payment_info' => [
                'gcash_number' => config('booking.gcash_number'),
                'gcash_qr_url' => config('booking.gcash_qr_url'),
            ],
        ], 201);
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        return $booking->load(['court', 'slots', 'statusLogs']);
    }

    public function submitGcashReference(SubmitGcashReferenceRequest $request, Booking $booking)
    {
        $booking = $this->bookings->submitGcashReference($booking, $request->string('gcash_reference'));

        return response()->json(['data' => $booking]);
    }

    public function checkinQr(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_unless($booking->status === 'confirmed', 404);

        return response()->json([
            'checkin_token' => $booking->checkin_token,
            'checkin_url' => url("/checkin/{$booking->checkin_token}"),
            'expires_at' => $booking->checkin_token_expires_at,
        ]);
    }
}
