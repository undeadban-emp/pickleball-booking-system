<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\OperatingHours;
use App\Models\PaymentMethod;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicBookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function show(string $token)
    {
        $booking = Booking::where('receipt_token', $token)
            ->with(['court', 'slots', 'statusLogs', 'paymentMethod'])
            ->firstOrFail();

        $paymentHoldMinutes = OperatingHours::current()->payment_hold_minutes ?? 30;
        $booking = $this->bookings->expireBookingIfStale($booking, $paymentHoldMinutes);

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('bookings.show', [
            'booking' => $booking,
            'paymentMethods' => $paymentMethods,
            'paymentHoldMinutes' => $paymentHoldMinutes,
        ]);
    }

    /**
     * Lightweight polling endpoint so the payment page can auto-detect status
     * changes (admin approval/rejection) without a full page reload. Also
     * lazily expires this booking the instant its own countdown runs out,
     * rather than waiting on the once-a-minute scheduled sweep.
     */
    public function status(string $token)
    {
        $booking = Booking::where('receipt_token', $token)->firstOrFail();

        $holdMinutes = OperatingHours::current()->payment_hold_minutes ?? 30;
        $booking = $this->bookings->expireBookingIfStale($booking, $holdMinutes);

        return response()
            ->json([
                'status' => $booking->status,
                'has_reference' => filled($booking->gcash_reference),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function submitReference(Request $request, string $token)
    {
        $booking = Booking::where('receipt_token', $token)->firstOrFail();

        $data = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'gcash_reference' => ['required', 'string', 'min:6', 'max:40'],
            'proof_of_payment' => ['nullable', 'image', 'max:4096'],
        ]);

        $proofPath = null;

        if ($request->hasFile('proof_of_payment')) {
            if ($booking->payment_proof_path) {
                Storage::disk('public')->delete($booking->payment_proof_path);
            }

            $proofPath = $request->file('proof_of_payment')->store('payment-proofs', 'public');
        }

        try {
            $this->bookings->submitGcashReference($booking, $data['gcash_reference'], $proofPath, (int) $data['payment_method_id']);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['gcash_reference' => $e->getMessage()]);
        }

        return redirect()->route('booking.public', $token)
            ->with('status', 'Reference submitted. We will confirm your booking shortly.');
    }

    public function cancel(Request $request, string $token)
    {
        $booking = Booking::where('receipt_token', $token)->firstOrFail();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->bookings->cancel($booking, null, $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('booking.public', $token)
            ->with('status', 'Your booking has been cancelled.');
    }
}
