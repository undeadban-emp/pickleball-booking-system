<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\InvalidBookingTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request)
    {
        return Booking::query()
            ->with(['court:id,name', 'user:id,name,phone,email', 'slots' => fn ($q) => $q->orderBy('start_time')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('court_id'), fn ($q) => $q->where('court_id', $request->integer('court_id')))
            ->latest()
            ->paginate(20)
            ->through(fn (Booking $booking) => $booking->toSummaryArray());
    }

    public function latest(Request $request)
    {
        $lastId = $request->integer('last_id', 0);

        $bookings = Booking::query()
            ->with(['court', 'user:id,name'])
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()
            ->json(['data' => $bookings])
            ->header('Cache-Control', 'no-store');
    }

    public function approve(Request $request, Booking $booking)
    {
        try {
            $booking = $this->bookings->approve($booking, $request->user());
        } catch (InvalidBookingTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $booking]);
    }

    public function reject(RejectBookingRequest $request, Booking $booking)
    {
        try {
            $booking = $this->bookings->reject($booking, $request->user(), $request->string('reason')->value() ?: null);
        } catch (InvalidBookingTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $booking]);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $booking = $this->bookings->cancel($booking, $request->user(), $request->string('reason')->value() ?: null);
        } catch (InvalidBookingTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $booking]);
    }
}
