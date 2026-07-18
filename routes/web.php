<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CheckinController as AdminCheckinController;
use App\Http\Controllers\Admin\CourtController as AdminCourtController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HeroImageController;
use App\Http\Controllers\Admin\MatchController;
use App\Http\Controllers\Admin\MatchGameController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Models\HeroImage;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CourtBookingController;
use App\Http\Controllers\Customer\BookingController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\QuickBookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'heroImages' => HeroImage::orderBy('sort_order')->orderBy('id')->get(),
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

// Court browsing + the fuller multi-slot calendar picker
Route::get('/book', [CourtBookingController::class, 'index'])->name('book.index');
Route::get('/book/{court}', [CourtBookingController::class, 'show'])->name('book.show');
Route::post('/book/{court}', [CourtBookingController::class, 'store'])->name('book.store');

Route::middleware('auth')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
});

Route::middleware(['auth', 'role:admin,staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/latest', [AdminBookingController::class, 'latest'])->name('bookings.latest');

    Route::get('/checkin', [AdminCheckinController::class, 'index'])->name('checkin.index');
    Route::post('/checkin/{booking}/confirm', [AdminCheckinController::class, 'confirm'])->name('checkin.confirm');

    // Booking decisions: admin and staff both manage the booking queue.
    Route::post('/bookings/{booking}/approve', [AdminBookingController::class, 'approve'])->name('bookings.approve');
    Route::post('/bookings/{booking}/reject', [AdminBookingController::class, 'reject'])->name('bookings.reject');
    Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');

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
    });
});
