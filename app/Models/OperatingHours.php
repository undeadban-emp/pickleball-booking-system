<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OperatingHours extends Model
{
    protected $fillable = [
        'open_time',
        'close_time',
        'slot_length_minutes',
        'gcash_number',
        'gcash_qr_path',
        'logo_path',
        'logo_height',
        'show_brand_text',
        'brand_text',
        'booking_widget_style',
        'period_morning_start',
        'period_afternoon_start',
        'period_evening_start',
        'period_late_evening_start',
    ];

    protected function casts(): array
    {
        return [
            'show_brand_text' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::first() ?? static::create([
            'open_time' => '06:00:00',
            'close_time' => '22:00:00',
            'slot_length_minutes' => 60,
        ]);
    }

    public function gcashQrUrl(): ?string
    {
        return $this->gcash_qr_path ? asset('storage/'.$this->gcash_qr_path) : null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    /**
     * Time-of-day period boundaries for the booking widget, as HH:MM strings.
     */
    public function periodBoundaries(): array
    {
        return [
            'morning' => substr($this->period_morning_start, 0, 5),
            'afternoon' => substr($this->period_afternoon_start, 0, 5),
            'evening' => substr($this->period_evening_start, 0, 5),
            'late' => substr($this->period_late_evening_start, 0, 5),
        ];
    }

    /**
     * Human-friendly "6am to 12 noon" style ranges for each period, in display order,
     * clipped to the venue's actual open ("Morning starts") to close ("Closes at")
     * window. A period entirely outside that window (e.g. "Late evening" when the
     * venue doesn't stay open past midnight) is flagged with has_slots = false rather
     * than shown as a misleading time range.
     */
    public function periodRanges(): array
    {
        $bounds = [
            'Morning' => $this->period_morning_start,
            'Afternoon' => $this->period_afternoon_start,
            'Evening' => $this->period_evening_start,
            'Late evening' => $this->period_late_evening_start,
        ];

        $openMinutes = $this->toMinutes($this->open_time);
        $closeMinutes = $this->toMinutes($this->close_time);
        if ($closeMinutes <= $openMinutes) {
            $closeMinutes += 1440;
        }

        // Place each period on the continuous open->close timeline. Anything that
        // starts before "open" clock-time belongs to the following day's cycle.
        $placed = [];
        foreach ($bounds as $label => $start) {
            $startMinutes = $this->toMinutes($start);
            if ($startMinutes < $openMinutes) {
                $startMinutes += 1440;
            }
            $placed[] = ['label' => $label, 'start' => $startMinutes];
        }
        usort($placed, fn ($a, $b) => $a['start'] <=> $b['start']);

        $ranges = [];
        foreach ($placed as $i => $period) {
            $end = $i < count($placed) - 1 ? $placed[$i + 1]['start'] : $openMinutes + 1440;

            $cappedStart = min($period['start'], $closeMinutes);
            $cappedEnd = min($end, $closeMinutes);

            $ranges[$period['label']] = [
                'label' => $period['label'],
                'from' => $this->friendlyTime($cappedStart),
                'to' => $this->friendlyTime($cappedEnd),
                'has_slots' => $cappedEnd > $cappedStart,
            ];
        }

        // Return in the fixed display order regardless of computed chronological order.
        return array_values(array_replace(array_flip(array_keys($bounds)), $ranges));
    }

    protected function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return ((int) $h * 60) + (int) $m;
    }

    protected function friendlyTime(int $minutes): string
    {
        $minutes %= 1440;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h === 0 && $m === 0) {
            return '12 midnight';
        }

        if ($h === 12 && $m === 0) {
            return '12 noon';
        }

        $t = Carbon::createFromTime($h, $m);

        return strtolower($t->format('g:ia'));
    }
}
