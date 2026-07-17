<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.payment-methods.index', ['methods' => $methods]);
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

        PaymentMethod::create($data + ['is_active' => true]);

        return back()->with('status', "{$data['name']} added.");
    }

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

        return back()->with('status', "{$paymentMethod->name} updated.");
    }

    public function toggleActive(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return back()->with('status', $paymentMethod->is_active
            ? "{$paymentMethod->name} is now available to customers."
            : "{$paymentMethod->name} is now hidden from customers.");
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->bookings()->exists()) {
            return back()->withErrors(['payment_method' => "{$paymentMethod->name} has bookings attached and can't be deleted. Deactivate it instead."]);
        }

        if ($paymentMethod->qr_path) {
            Storage::disk('public')->delete($paymentMethod->qr_path);
        }

        $paymentMethod->delete();

        return back()->with('status', 'Payment method removed.');
    }
}
