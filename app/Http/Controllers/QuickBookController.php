<?php

namespace App\Http\Controllers;

use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuickBookController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function store(Request $request)
    {
        $rules = [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'court_slot_ids' => ['required', 'array', 'min:1', 'max:6'],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
        ];

        if (! Auth::check()) {
            $rules['guest_name'] = ['required', 'string', 'max:120'];
            $rules['guest_phone'] = ['required', 'string', 'max:30'];
            $rules['guest_email'] = ['required', 'email', 'max:150'];
        }

        $data = $request->validate($rules);

        $court = Court::findOrFail($data['court_id']);

        $guest = Auth::check() ? null : [
            'name' => $data['guest_name'],
            'phone' => $data['guest_phone'],
            'email' => $data['guest_email'] ?? null,
        ];

        try {
            $booking = $this->bookings->createBooking(Auth::user(), $court, $data['court_slot_ids'], $guest);
        } catch (NonContiguousSlotsException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        } catch (SlotUnavailableException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        }

        return redirect()->route('booking.public', $booking->receipt_token);
    }
}
