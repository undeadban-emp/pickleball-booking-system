<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => $methods]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'account_number' => ['required', 'string', 'max:60'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'qr' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('qr')) {
            $data['qr_path'] = $request->file('qr')->store('payment-methods', 'public');
        }

        $method = PaymentMethod::create($data + ['is_active' => true]);

        return response()->json(['data' => $method], 201);
    }

    // POST (not PUT) so multipart file uploads work reliably from mobile clients.
    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'account_number' => ['required', 'string', 'max:60'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'qr' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('qr')) {
            if ($paymentMethod->qr_path) {
                Storage::disk('public')->delete($paymentMethod->qr_path);
            }

            $data['qr_path'] = $request->file('qr')->store('payment-methods', 'public');
        }

        $paymentMethod->update($data);

        return response()->json(['data' => $paymentMethod]);
    }

    public function toggleActive(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return response()->json(['data' => $paymentMethod]);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->bookings()->exists()) {
            return response()->json([
                'message' => "{$paymentMethod->name} has bookings attached and can't be deleted. Deactivate it instead.",
            ], 422);
        }

        if ($paymentMethod->qr_path) {
            Storage::disk('public')->delete($paymentMethod->qr_path);
        }

        $paymentMethod->delete();

        return response()->json(['message' => 'Payment method removed.']);
    }
}
