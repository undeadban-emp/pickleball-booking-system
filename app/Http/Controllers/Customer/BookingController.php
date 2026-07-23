<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function poll(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter();

        $bookings = $ids->isNotEmpty()
            ? $user->bookings()->whereIn('id', $ids)->get(['id', 'status', 'gcash_reference'])
            : collect();

        return response()
            ->json([
                'pending_count' => $user->bookings()->where('status', 'pending_payment')->count(),
                'statuses' => $bookings->pluck('status', 'id'),
                'has_reference' => $bookings->mapWithKeys(fn ($b) => [$b->id => filled($b->gcash_reference)]),
            ])
            ->header('Cache-Control', 'no-store');
    }
}
