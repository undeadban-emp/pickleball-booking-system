<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        $bookings = $user
            ->bookings()
            ->with(['court', 'slots'])
            ->latest()
            ->paginate(10);

        return view('bookings.index', ['bookings' => $bookings]);
    }
}
