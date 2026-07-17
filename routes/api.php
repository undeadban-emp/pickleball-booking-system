<?php

use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\CourtController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public: browsing courts and availability
Route::get('/courts', [CourtController::class, 'index']);
Route::get('/courts/{court}/slots', [CourtController::class, 'slots']);
Route::get('/availability', [AvailabilityController::class, 'index']);

// Customer booking
Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::get('/bookings/mine', [BookingController::class, 'mine']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/gcash-reference', [BookingController::class, 'submitGcashReference']);
    Route::get('/bookings/{booking}/checkin-qr', [BookingController::class, 'checkinQr']);
});

// Admin booking management
Route::middleware(['auth:sanctum', 'role:admin,staff'])->prefix('admin')->group(function () {
    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::get('/bookings/latest', [AdminBookingController::class, 'latest']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::post('/bookings/{booking}/approve', [AdminBookingController::class, 'approve']);
    Route::post('/bookings/{booking}/reject', [AdminBookingController::class, 'reject']);
    Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel']);
});

// Front-desk check-in (admin or staff)
Route::middleware(['auth:sanctum', 'role:admin,staff'])->prefix('checkin')->group(function () {
    Route::post('/validate', [CheckinController::class, 'validateToken']);
    Route::post('/{booking}/confirm', [CheckinController::class, 'confirm']);
});
