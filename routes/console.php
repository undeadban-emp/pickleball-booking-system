<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the rolling booking-availability window extending forward, and picks
// up any operating-hours change (new slots for a widened window) each day.
Schedule::command('courts:generate-slots')->daily();

// Releases slots held by unpaid bookings once the admin's payment window
// passes, so they go back to available for someone else to book.
Schedule::command('bookings:expire-pending')->everyMinute();
