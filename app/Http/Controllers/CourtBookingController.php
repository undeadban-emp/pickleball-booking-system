<?php

namespace App\Http\Controllers;

use App\Exceptions\NonContiguousSlotsException;
use App\Exceptions\SlotUnavailableException;
use App\Models\BookingOrder;
use App\Models\Court;
use App\Models\OperatingHours;
use App\Services\BookingOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourtBookingController extends Controller
{
    public function __construct(protected BookingOrderService $checkout) {}

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
        $settings = OperatingHours::current();

        return view('book.show', [
            'court' => $court,
            'periodBoundaries' => $settings->periodBoundaries(),
            'periodEnds' => $settings->periodEnds(),
            'maxBookingHours' => $settings->max_customer_booking_hours ?? 24,
        ]);
    }

    public function store(Request $request, Court $court)
    {
        $data = $request->validate([
            'court_slot_ids' => ['required', 'array', 'min:1', 'max:'.(OperatingHours::current()->max_customer_booking_hours ?? 24)],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
        ]);

        if (! Auth::check()) {
            session(['pending_booking' => [
                'court_id' => $court->id,
                'court_slot_ids' => $data['court_slot_ids'],
            ]]);

            return redirect()->route('login')->with('status', 'Please log in or create an account to complete your booking.');
        }

        try {
            $result = $this->checkout->checkout(Auth::user(), $court, $data['court_slot_ids']);
        } catch (NonContiguousSlotsException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        } catch (SlotUnavailableException $e) {
            return back()->withErrors(['court_slot_ids' => $e->getMessage()])->withInput();
        }

        return $result instanceof BookingOrder
            ? redirect()->route('order.public', $result->receipt_token)
            : redirect()->route('booking.public', $result->receipt_token);
    }
}
