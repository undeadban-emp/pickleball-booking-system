<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidBookingTransitionException;
use App\Models\BookingOrder;
use App\Models\OperatingHours;
use App\Models\PaymentMethod;
use App\Services\BookingOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicOrderController extends Controller
{
    public function __construct(protected BookingOrderService $bookingOrders) {}

    public function show(string $token)
    {
        $order = BookingOrder::where('receipt_token', $token)
            ->with([
                'bookings.court', 'bookings.slots', 'bookings.rescheduleLogs', 'paymentMethod',
                'bookings.holds' => fn ($q) => $q->whereNull('resolved_at')->with('fromCourt:id,name'),
            ])
            ->firstOrFail();

        $paymentHoldMinutes = OperatingHours::current()->payment_hold_minutes ?? 30;
        $order = $this->bookingOrders->expireOrderIfStale($order, $paymentHoldMinutes);

        // Orders confirmed before the single-order-QR feature shipped won't
        // have a checkin_token yet - backfill it lazily instead of showing a
        // broken QR block.
        if ($order->status === 'confirmed' && ! $order->checkin_token) {
            $order = $this->bookingOrders->backfillCheckinToken($order);
        }

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('orders.show', [
            'order' => $order,
            'paymentMethods' => $paymentMethods,
            'paymentHoldMinutes' => $paymentHoldMinutes,
        ]);
    }

    /**
     * Same purpose as PublicBookingController::status() - lets the payment
     * page auto-detect admin approval/rejection without a full reload.
     */
    public function status(string $token)
    {
        $order = BookingOrder::where('receipt_token', $token)->firstOrFail();

        $holdMinutes = OperatingHours::current()->payment_hold_minutes ?? 30;
        $order = $this->bookingOrders->expireOrderIfStale($order, $holdMinutes);

        return response()
            ->json([
                'status' => $order->status,
                'has_reference' => $order->hasSubmittedPayment(),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function submitReference(Request $request, string $token)
    {
        $order = BookingOrder::where('receipt_token', $token)->firstOrFail();

        $data = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'gcash_reference' => ['nullable', 'string', 'min:6', 'max:40'],
            'proof_of_payment' => ['required', 'image', 'max:4096'],
        ]);

        $proofPath = null;

        if ($request->hasFile('proof_of_payment')) {
            if ($order->payment_proof_path) {
                Storage::disk('public')->delete($order->payment_proof_path);
            }

            $proofPath = $request->file('proof_of_payment')->store('payment-proofs', 'public');
        }

        try {
            $this->bookingOrders->submitGcashReference($order, $data['gcash_reference'] ?? null, $proofPath, (int) $data['payment_method_id']);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['gcash_reference' => $e->getMessage()]);
        }

        return redirect()->route('order.public', $token)
            ->with('status', 'Reference submitted. We will confirm your booking shortly.');
    }

    public function cancel(Request $request, string $token)
    {
        $order = BookingOrder::where('receipt_token', $token)->firstOrFail();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->bookingOrders->cancel($order, null, $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->route('order.public', $token)
            ->with('status', 'Your booking has been cancelled.');
    }
}
