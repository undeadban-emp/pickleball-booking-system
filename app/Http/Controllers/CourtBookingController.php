<?php

namespace App\Http\Controllers;

use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourtBookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index()
    {
        $courts = Court::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('book.index', ['courts' => $courts]);
    }

    public function show(Court $court)
    {
        return view('book.show', ['court' => $court]);
    }

    public function store(Request $request, Court $court)
    {
        $rules = [
            'court_slot_ids' => ['required', 'array', 'min:1', 'max:6'],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
        ];

        if (! Auth::check()) {
            $rules['guest_name'] = ['required', 'string', 'max:120'];
            $rules['guest_phone'] = ['required', 'string', 'max:30'];
            $rules['guest_email'] = ['required', 'email', 'max:150'];
        }

        $data = $request->validate($rules);

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
