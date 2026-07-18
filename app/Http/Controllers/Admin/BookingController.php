<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function index(Request $request)
    {
        $bookings = Booking::query()
            ->with(['court', 'user:id,name,phone,email', 'slots', 'statusLogs.changedBy:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('court_id'), fn ($q) => $q->where('court_id', $request->integer('court_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('booking_code', 'like', "%{$term}%")
                        ->orWhere('guest_name', 'like', "%{$term}%")
                        ->orWhere('guest_phone', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'courts' => Court::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'court_id', 'search']),
        ]);
    }

    /**
     * Incremental polling endpoint for the admin bookings screen. Returns only
     * bookings with id > last_id, so the client can detect new arrivals without
     * refetching the whole list. Session-authenticated (not Sanctum), since the
     * admin panel is a plain server-rendered web app, not an SPA/API client.
     */
    public function latest(Request $request)
    {
        $lastId = $request->integer('last_id', 0);

        $bookings = Booking::query()
            ->with(['court:id,name'])
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'booking_code', 'court_id', 'status', 'total_price', 'created_at'])
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'court' => $b->court->name,
                'status' => $b->status,
                'total_price' => $b->total_price,
                'contact' => $b->contactName(),
            ]);

        return response()
            ->json(['data' => $bookings])
            ->header('Cache-Control', 'no-store');
    }

    public function approve(Booking $booking)
    {
        $this->authorizeCanManageBookings();

        try {
            $this->bookings->approve($booking, Auth::user());
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->booking_code} confirmed.");
    }

    public function reject(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->bookings->reject($booking, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->booking_code} rejected.");
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeCanManageBookings();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->bookings->cancel($booking, Auth::user(), $data['reason'] ?? null);
        } catch (InvalidBookingTransitionException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', "Booking {$booking->booking_code} cancelled.");
    }

    protected function authorizeCanManageBookings(): void
    {
        abort_unless(Auth::user()->isAdmin() || Auth::user()->isStaff(), 403);
    }
}
