<?php

use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\BookingReportController as AdminBookingReportController;
use App\Http\Controllers\Api\Admin\ClientReportController as AdminClientReportController;
use App\Http\Controllers\Api\Admin\CourtController as AdminCourtController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\HeroImageController as AdminHeroImageController;
use App\Http\Controllers\Api\Admin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Api\Admin\RevenueReportController as AdminRevenueReportController;
use App\Http\Controllers\Api\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\AppUpdateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\OpenPlayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: browsing courts and availability. Also consumed directly by the
// server-rendered website's browser JS (booking-calendar.js, availability-grid.js
// via fetch()), which can't carry the Flutter app's embedded X-Jocos-Token —
// so these stay outside the app.token gate below, same as before it existed.
Route::middleware('throttle:availability-read')->group(function () {
    Route::get('/courts', [CourtController::class, 'index']);
    Route::get('/courts/{court}/slots', [CourtController::class, 'slots']);
    Route::get('/availability', [AvailabilityController::class, 'index']);
});

// Everything else requires the official Kitchen Line app header. It only
// proves "this is our app build", not "this is a trusted user" — real
// authorization still comes from auth:sanctum + role below. None of this is
// touched by the website's own JS, only the Flutter app.
Route::middleware(['app.token', 'throttle:api'])->group(function () {

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::get('/app/latest-version', [AppUpdateController::class, 'latest']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/device-token', [AuthController::class, 'deviceToken']);

        Route::get('/user', fn (Request $request) => $request->user());
    });

    // Customer: book + list their own bookings
    Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::get('/bookings/mine', [BookingController::class, 'mine']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/bookings/{booking}/gcash-reference', [BookingController::class, 'submitGcashReference']);
        Route::get('/bookings/{booking}/checkin-qr', [BookingController::class, 'checkinQr']);

        // Open Play: hosted from a customer's own confirmed booking, no
        // separate admin surface - see routes/web.php for the same split.
        Route::get('/open-play/rooms', [OpenPlayController::class, 'index']);
        Route::get('/open-play/rooms/{room}', [OpenPlayController::class, 'show']);
        Route::post('/open-play/rooms', [OpenPlayController::class, 'store']);
        Route::post('/open-play/rooms/{room}/join', [OpenPlayController::class, 'join']);
        Route::post('/open-play/rooms/{room}/leave', [OpenPlayController::class, 'leave']);
        Route::post('/open-play/rooms/{room}/check-in', [OpenPlayController::class, 'checkIn']);
        Route::post('/open-play/rooms/{room}/start', [OpenPlayController::class, 'start']);
        Route::post('/open-play/rooms/{room}/matches/{match}/complete', [OpenPlayController::class, 'completeMatch']);
        Route::post('/open-play/rooms/{room}/end', [OpenPlayController::class, 'end']);
        Route::get('/open-play/rooms/{room}/dashboard', [OpenPlayController::class, 'dashboard']);
        Route::get('/open-play/rooms/{room}/summary', [OpenPlayController::class, 'summary']);
        Route::get('/open-play/history', [OpenPlayController::class, 'history']);
        Route::get('/open-play/players/{user}', [OpenPlayController::class, 'player']);
    });

    // Staff + admin: booking oversight and front-desk check-in
    Route::middleware(['auth:sanctum', 'role:admin,staff'])->group(function () {
        Route::prefix('admin')->group(function () {
            Route::get('/bookings', [AdminBookingController::class, 'index']);
            Route::get('/bookings/latest', [AdminBookingController::class, 'latest']);
            Route::get('/schedule', [AdminScheduleController::class, 'index']);
        });

        Route::prefix('checkin')->group(function () {
            Route::post('/validate', [CheckinController::class, 'validateToken']);
        });

        // Booking decisions: admin and staff both manage the booking queue.
        Route::prefix('admin')->group(function () {
            Route::post('/bookings/{booking}/approve', [AdminBookingController::class, 'approve']);
            Route::post('/bookings/{booking}/reject', [AdminBookingController::class, 'reject']);
            Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel']);
        });
    });

    // Admin-only: dashboard, reports, court/settings/catalog management
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::get('/courts', [AdminCourtController::class, 'index']);
        Route::post('/courts', [AdminCourtController::class, 'store']);
        Route::put('/courts/{court}', [AdminCourtController::class, 'update']);
        Route::post('/courts/{court}/maintenance', [AdminCourtController::class, 'toggleMaintenance']);
        Route::post('/courts/{court}/toggle-active', [AdminCourtController::class, 'toggleActive']);

        Route::get('/reports/bookings', [AdminBookingReportController::class, 'index']);
        Route::get('/reports/revenue', [AdminRevenueReportController::class, 'index']);
        Route::get('/reports/clients', [AdminClientReportController::class, 'index']);

        Route::get('/settings', [AdminSettingsController::class, 'show']);
        Route::post('/settings', [AdminSettingsController::class, 'update']);
        Route::post('/settings/logo/remove', [AdminSettingsController::class, 'removeLogo']);
        Route::post('/settings/hours', [AdminSettingsController::class, 'updateHours']);
        Route::post('/settings/location', [AdminSettingsController::class, 'updateLocation']);
        Route::get('/settings/rates', [AdminCourtController::class, 'rates']);
        Route::put('/settings/rates/{court}', [AdminCourtController::class, 'updateRate']);

        Route::get('/payment-methods', [AdminPaymentMethodController::class, 'index']);
        Route::post('/payment-methods', [AdminPaymentMethodController::class, 'store']);
        // POST (not PUT) on update so multipart QR-image uploads work from mobile clients.
        Route::post('/payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'update']);
        Route::post('/payment-methods/{paymentMethod}/toggle-active', [AdminPaymentMethodController::class, 'toggleActive']);
        Route::delete('/payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'destroy']);

        Route::get('/hero-images', [AdminHeroImageController::class, 'index']);
        Route::post('/hero-images', [AdminHeroImageController::class, 'store']);
        Route::post('/hero-images/{heroImage}/move-up', [AdminHeroImageController::class, 'moveUp']);
        Route::post('/hero-images/{heroImage}/move-down', [AdminHeroImageController::class, 'moveDown']);
        Route::delete('/hero-images/{heroImage}', [AdminHeroImageController::class, 'destroy']);
    });
});
