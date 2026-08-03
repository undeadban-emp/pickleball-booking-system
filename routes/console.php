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

// Closes Open Play sessions the host forgot to end - a 12-hour inactivity
// window doesn't need per-minute polling, hourly is plenty.
Schedule::command('open-play:auto-end-stale')->hourly();

// Processes queued emails (see BookingService::sendAndLogEmail()) without
// needing a standalone `queue:work` daemon/Supervisor process - piggybacks
// on the cron that's already driving this scheduler every minute.
// --stop-when-empty exits once the queue is drained instead of idling, so
// it doesn't overlap with the next minute's run.
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
