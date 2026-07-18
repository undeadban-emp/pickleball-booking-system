<?php

namespace App\Console\Commands;

use App\Models\Court;
use App\Models\OperatingHours;
use App\Support\SlotGenerator;
use Illuminate\Console\Command;

/**
 * SlotGenerator::generate() only ever ran once, at court-creation time, which
 * fills a fixed window from that moment (default 60 days) and never moves
 * forward. Run this daily so the booking calendar always has a rolling
 * window of open days instead of running out a couple months after a court
 * was created. firstOrCreate() inside SlotGenerator makes re-running safe —
 * existing rows are left untouched, only the new trailing day is added.
 */
class GenerateCourtSlots extends Command
{
    protected $signature = 'courts:generate-slots';

    protected $description = "Extend every court's rolling availability window by generating its next batch of slots";

    public function handle(): int
    {
        $hours = OperatingHours::current();

        Court::all()->each(function (Court $court) use ($hours) {
            SlotGenerator::generate($court, $hours);
            $this->info("Generated slots for {$court->name}.");
        });

        return self::SUCCESS;
    }
}
