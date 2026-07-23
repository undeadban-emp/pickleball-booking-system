<?php

namespace App\Support;

use App\Models\Court;
use App\Models\CourtSlot;
use App\Models\OperatingHours;

class SlotGenerator
{
    /**
     * Generate available slots for a court across a rolling window of days,
     * based on the "Morning starts" / "Closes at" boundaries in OperatingHours.
     * "Closes at" may be earlier in clock-time than "Morning starts" (e.g. 6am
     * open, 2am close) - that means the schedule runs past midnight, and the
     * late-night slots still belong to the business day they started on.
     */
    public static function generate(Court $court, OperatingHours $hours, int $days = 60): void
    {
        $openMinutes = static::toMinutes($hours->open_time);
        $closeMinutes = static::toMinutes($hours->close_time);

        if ($closeMinutes <= $openMinutes) {
            $closeMinutes += 1440;
        }

        $length = $hours->slot_length_minutes;

        for ($day = 0; $day < $days; $day++) {
            $date = today()->addDays($day)->toDateString();

            for ($minutes = $openMinutes; $minutes < $closeMinutes; $minutes += $length) {
                $start = static::toTimeString($minutes);
                $end = static::toTimeString($minutes + $length);

                CourtSlot::firstOrCreate(
                    [
                        'court_id' => $court->id,
                        'slot_date' => $date,
                        'start_time' => $start,
                    ],
                    [
                        'end_time' => $end,
                        'price' => $court->default_price,
                        'status' => 'available',
                    ]
                );
            }
        }
    }

    /**
     * Delete still-open (available, unbooked) slots whose start time no longer
     * falls within the current open->close window - e.g. after operating hours
     * are narrowed, or the slot length changes so old start times no longer
     * align. Booked/pending slots are never touched; they're historical.
     */
    public static function pruneOutOfWindow(OperatingHours $hours): void
    {
        $openMinutes = static::toMinutes($hours->open_time);
        $closeMinutes = static::toMinutes($hours->close_time);

        if ($closeMinutes <= $openMinutes) {
            $closeMinutes += 1440;
        }

        $validTimes = [];
        for ($minutes = $openMinutes; $minutes < $closeMinutes; $minutes += $hours->slot_length_minutes) {
            $validTimes[] = static::toTimeString($minutes);
        }

        CourtSlot::where('status', 'available')
            ->whereNotIn('start_time', $validTimes)
            ->delete();
    }

    protected static function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return ((int) $h * 60) + (int) $m;
    }

    protected static function toTimeString(int $minutes): string
    {
        $minutes %= 1440;

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
