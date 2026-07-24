<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\BookingReportController;
use App\Http\Controllers\Admin\CheckinController as AdminCheckinController;
use App\Http\Controllers\Admin\ClientReportController;
use App\Http\Controllers\Admin\CourtController as AdminCourtController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GalleryImageController;
use App\Http\Controllers\Admin\HeroImageController;
use App\Http\Controllers\Admin\MatchController;
use App\Http\Controllers\Admin\MatchGameController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\OpenPlayController;
use App\Http\Controllers\Admin\RevenueReportController;
use App\Models\Court;
use App\Models\GalleryImage;
use App\Models\HeroImage;
use App\Models\OperatingHours;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CourtBookingController;
use App\Http\Controllers\Customer\BookingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\QuickBookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $settings = OperatingHours::current();

    return view('welcome', [
        'activeCourtsCount' => Court::where('is_active', true)->count(),
        'heroImages' => HeroImage::orderBy('sort_order')->orderBy('id')->get(),
        // Newest upload first on the homepage - independent of the admin's
        // own manual sort_order (used for reordering in the Album admin page).
        'galleryImages' => GalleryImage::orderByDesc('created_at')->orderByDesc('id')->get(),
        'mapLat' => $settings->map_lat,
        'mapLng' => $settings->map_lng,
        'mapStyle' => $settings->map_style ?? 'satellite',
        'mapLabel' => $settings->map_location
            ?: Court::where('is_active', true)->whereNotNull('location')->value('location'),
    ]);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Guest-friendly quick booking (no account required)
Route::post('/quick-book', [QuickBookController::class, 'store'])->name('quick-book');

// Public booking detail/payment page, reachable by anyone holding the link
Route::get('/b/{token}', [PublicBookingController::class, 'show'])->name('booking.public');
Route::get('/b/{token}/status', [PublicBookingController::class, 'status'])->name('booking.public.status');
Route::post('/b/{token}/gcash-reference', [PublicBookingController::class, 'submitReference'])->name('booking.public.gcash-reference');
Route::post('/b/{token}/cancel', [PublicBookingController::class, 'cancel'])->name('booking.public.cancel');

// Same, but for a multi-session checkout (BookingOrder) - one payment step
// covering several underlying bookings, each of which still has its own
// /b/{token} receipt/QR above.
Route::get('/o/{token}', [PublicOrderController::class, 'show'])->name('order.public');
Route::get('/o/{token}/status', [PublicOrderController::class, 'status'])->name('order.public.status');
Route::post('/o/{token}/gcash-reference', [PublicOrderController::class, 'submitReference'])->name('order.public.gcash-reference');
Route::post('/o/{token}/cancel', [PublicOrderController::class, 'cancel'])->name('order.public.cancel');

// Court browsing + the fuller multi-slot calendar picker
Route::get('/book', [CourtBookingController::class, 'index'])->name('book.index');
Route::get('/book/{court}', [CourtBookingController::class, 'show'])->name('book.show');
Route::post('/book/{court}', [CourtBookingController::class, 'store'])->name('book.store');

Route::middleware('auth')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/poll', [BookingController::class, 'poll'])->name('bookings.poll');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

// Open Play: hosted by whichever customer already holds the confirmed
// booking for that court/time - browsing/viewing a public room is open to
// anyone, everything else (creating, joining, running a session) requires
// login. Static sub-paths (create/history/players/{user}) are registered
// before the dynamic {room} show route below, so e.g. "/open-play/history"
// isn't swallowed by {room}.
Route::get('/open-play', [OpenPlayController::class, 'index'])->name('open-play.index');

Route::middleware('auth')->prefix('open-play')->name('open-play.')->group(function () {
    Route::get('/create', [OpenPlayController::class, 'create'])->name('create');
    Route::post('/', [OpenPlayController::class, 'store'])->name('store');
    Route::get('/history', [OpenPlayController::class, 'history'])->name('history');
    Route::get('/players/{user}', [OpenPlayController::class, 'player'])->name('player');
});

Route::get('/open-play/{room}', [OpenPlayController::class, 'show'])->name('open-play.show');

Route::middleware('auth')->prefix('open-play')->name('open-play.')->group(function () {
    Route::post('/{room}/join', [OpenPlayController::class, 'join'])->name('join');
    Route::post('/{room}/leave', [OpenPlayController::class, 'leave'])->name('leave');
    Route::post('/{room}/check-in', [OpenPlayController::class, 'checkIn'])->name('check-in');
    Route::post('/{room}/players/{player}/check-in', [OpenPlayController::class, 'checkInPlayer'])->name('players.check-in');
    Route::post('/{room}/start', [OpenPlayController::class, 'start'])->name('start');
    Route::post('/{room}/matches/{match}/complete', [OpenPlayController::class, 'completeMatch'])->name('matches.complete');
    Route::post('/{room}/end', [OpenPlayController::class, 'end'])->name('end');
    Route::get('/{room}/dashboard', [OpenPlayController::class, 'dashboard'])->name('dashboard');
    Route::get('/{room}/dashboard/poll', [OpenPlayController::class, 'poll'])->name('dashboard.poll');
    Route::get('/{room}/summary', [OpenPlayController::class, 'summary'])->name('summary');
});

Route::middleware(['auth', 'role:admin,staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [AdminBookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [AdminBookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/schedule', [AdminBookingController::class, 'schedule'])->name('bookings.schedule');
    Route::get('/bookings/latest', [AdminBookingController::class, 'latest'])->name('bookings.latest');
    Route::get('/bookings/pending-count', [AdminBookingController::class, 'pendingCount'])->name('bookings.pending-count');

    Route::get('/checkin', [AdminCheckinController::class, 'index'])->name('checkin.index');
    Route::post('/checkin/{booking}/confirm', [AdminCheckinController::class, 'confirm'])->name('checkin.confirm');

    // Booking decisions: admin and staff both manage the booking queue.
    Route::post('/bookings/{booking}/approve', [AdminBookingController::class, 'approve'])->name('bookings.approve');
    Route::post('/bookings/{booking}/reject', [AdminBookingController::class, 'reject'])->name('bookings.reject');
    Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/bookings/orders/{order}/reschedule', [AdminBookingController::class, 'selectReschedule'])->name('bookings.reschedule.select');
    Route::get('/bookings/{booking}/reschedule', [AdminBookingController::class, 'editReschedule'])->name('bookings.reschedule.edit');
    Route::post('/bookings/{booking}/reschedule', [AdminBookingController::class, 'updateReschedule'])->name('bookings.reschedule.update');
    Route::post('/bookings/{booking}/split-reschedule', [AdminBookingController::class, 'updateSplitReschedule'])->name('bookings.split-reschedule.update');

    // Live match scoring on a checked-in booking: admin and staff both run these.
    Route::get('/bookings/{booking}/matches/create', [MatchController::class, 'create'])->name('matches.create');
    Route::post('/bookings/{booking}/matches', [MatchController::class, 'store'])->name('matches.store');
    Route::get('/matches/{match}', [MatchController::class, 'show'])->name('matches.show');
    Route::post('/matches/{match}/start', [MatchController::class, 'start'])->name('matches.start');
    Route::post('/matches/{match}/complete', [MatchController::class, 'complete'])->name('matches.complete');
    Route::get('/matches/{match}/results', [MatchController::class, 'results'])->name('matches.results');
    Route::post('/matches/{match}/games/{game}/point', [MatchGameController::class, 'point'])->name('matches.games.point');
    Route::post('/matches/{match}/games/{game}/side-out', [MatchGameController::class, 'sideOut'])->name('matches.games.side-out');
    Route::post('/matches/{match}/games/{game}/timeout', [MatchGameController::class, 'timeout'])->name('matches.games.timeout');
    Route::post('/matches/{match}/games/{game}/complete', [MatchGameController::class, 'complete'])->name('matches.games.complete');
    Route::post('/matches/{match}/games/{game}/rewind', [MatchGameController::class, 'rewind'])->name('matches.games.rewind');

    // Admin-only: court/settings/catalog management
    Route::middleware('role:admin')->group(function () {
        Route::get('/courts', [AdminCourtController::class, 'index'])->name('courts.index');
        Route::post('/courts', [AdminCourtController::class, 'store'])->name('courts.store');
        Route::put('/courts/{court}', [AdminCourtController::class, 'update'])->name('courts.update');
        Route::post('/courts/{court}/maintenance', [AdminCourtController::class, 'toggleMaintenance'])->name('courts.maintenance');
        Route::post('/courts/{court}/toggle-active', [AdminCourtController::class, 'toggleActive'])->name('courts.toggle-active');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/logo/remove', [SettingsController::class, 'removeLogo'])->name('settings.logo.remove');

        Route::get('/settings/hours', [SettingsController::class, 'editHours'])->name('settings.hours');
        Route::post('/settings/hours', [SettingsController::class, 'updateHours'])->name('settings.hours.update');

        Route::get('/settings/rates', [AdminCourtController::class, 'rates'])->name('settings.rates.index');
        Route::put('/settings/rates/{court}', [AdminCourtController::class, 'updateRate'])->name('settings.rates.update');

        Route::get('/settings/location', [SettingsController::class, 'editLocation'])->name('settings.location');
        Route::put('/settings/location', [SettingsController::class, 'updateLocation'])->name('settings.location.update');

        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::post('/payment-methods/{paymentMethod}/toggle-active', [PaymentMethodController::class, 'toggleActive'])->name('payment-methods.toggle-active');
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

        Route::get('/hero-images', [HeroImageController::class, 'index'])->name('hero-images.index');
        Route::post('/hero-images', [HeroImageController::class, 'store'])->name('hero-images.store');
        Route::post('/hero-images/{heroImage}/move-up', [HeroImageController::class, 'moveUp'])->name('hero-images.move-up');
        Route::post('/hero-images/{heroImage}/move-down', [HeroImageController::class, 'moveDown'])->name('hero-images.move-down');
        Route::delete('/hero-images/{heroImage}', [HeroImageController::class, 'destroy'])->name('hero-images.destroy');

        Route::get('/gallery-images', [GalleryImageController::class, 'index'])->name('gallery-images.index');
        Route::post('/gallery-images', [GalleryImageController::class, 'store'])->name('gallery-images.store');
        Route::post('/gallery-images/{galleryImage}/move-up', [GalleryImageController::class, 'moveUp'])->name('gallery-images.move-up');
        Route::post('/gallery-images/{galleryImage}/move-down', [GalleryImageController::class, 'moveDown'])->name('gallery-images.move-down');
        Route::delete('/gallery-images/{galleryImage}', [GalleryImageController::class, 'destroy'])->name('gallery-images.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/reports/bookings', [BookingReportController::class, 'index'])->name('reports.bookings');
        Route::get('/reports/bookings/export', [BookingReportController::class, 'export'])->name('reports.bookings.export');
        Route::get('/reports/bookings/pdf', [BookingReportController::class, 'pdf'])->name('reports.bookings.pdf');
        Route::get('/reports/revenue', [RevenueReportController::class, 'index'])->name('reports.revenue');
        Route::get('/reports/revenue/export', [RevenueReportController::class, 'export'])->name('reports.revenue.export');
        Route::get('/reports/revenue/pdf', [RevenueReportController::class, 'pdf'])->name('reports.revenue.pdf');
        Route::get('/reports/clients', [ClientReportController::class, 'index'])->name('reports.clients');
        Route::get('/reports/clients/export', [ClientReportController::class, 'export'])->name('reports.clients.export');
        Route::get('/reports/clients/pdf', [ClientReportController::class, 'pdf'])->name('reports.clients.pdf');
    });
});
