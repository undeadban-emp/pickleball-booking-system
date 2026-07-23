<?php

namespace App\Console\Commands;

use App\Models\OperatingHours;
use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireStalePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = "Cancel pending-payment bookings that have sat unpaid past the admin's configured payment hold window, freeing their slots";

    public function handle(BookingService $bookings): int
    {
        $holdMinutes = OperatingHours::current()->payment_hold_minutes;

        if (! $holdMinutes) {
            $this->info('No payment hold window configured; skipping.');

            return self::SUCCESS;
        }

        $count = $bookings->expireStalePending($holdMinutes);

        $this->info("Expired {$count} stale pending booking(s).");

        return self::SUCCESS;
    }
}
