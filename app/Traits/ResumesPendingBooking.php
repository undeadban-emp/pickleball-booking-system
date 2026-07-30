<?php

namespace App\Traits;

use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Models\BookingOrder;
use App\Models\Court;
use App\Services\BookingOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

trait ResumesPendingBooking
{
    protected function resumePendingBooking(): ?RedirectResponse
    {
        $pending = session()->pull('pending_booking');

        if (! $pending) {
            return null;
        }

        $court = Court::find($pending['court_id']);

        if (! $court) {
            return null;
        }

        $checkout = app(BookingOrderService::class);

        try {
            $result = $checkout->checkout(Auth::user(), $court, $pending['court_slot_ids']);
        } catch (NonContiguousSlotsException|SlotUnavailableException $e) {
            return redirect()->route('book.show', $court)->withErrors(['court_slot_ids' => $e->getMessage()]);
        }

        return $result instanceof BookingOrder
            ? redirect()->route('order.public', $result->receipt_token)
            : redirect()->route('booking.public', $result->receipt_token);
    }
}
