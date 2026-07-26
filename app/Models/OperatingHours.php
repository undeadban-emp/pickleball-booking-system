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
        'period_morning_end',
        'period_afternoon_start',
        'period_afternoon_end',
        'period_evening_start',
        'period_evening_end',
        'payment_hold_minutes',
        'max_customer_booking_hours',
        'map_location',
        'map_lat',
        'map_lng',
        'map_style',
        'facebook_url',
    ];

    protected function casts(): array
    {
        return [
            'show_brand_text' => 'boolean',
            'map_lat' => 'decimal:7',
            'map_lng' => 'decimal:7',
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
        ];
    }

    /**
     * End times matching periodBoundaries(), keyed the same way. Needed
     * alongside the starts so the widgets can tell a genuine overnight
     * spillover (evening wrapping past midnight) apart from hours that are
     * simply before the first configured period and shouldn't be labelled
     * with the last one just because nothing else matched.
     */
    public function periodEnds(): array
    {
        return [
            'morning' => substr($this->period_morning_end, 0, 5),
            'afternoon' => substr($this->period_afternoon_end, 0, 5),
            'evening' => substr($this->period_evening_end, 0, 5),
        ];
    }

    /**
     * Human-friendly "6am to 12 noon" style ranges for each period, using the
     * admin-configured start AND end for that period directly — not derived
     * from the next period's start, so what the admin sets is exactly what's
     * shown (periods are independent and may have gaps or overlap).
     */
    public function periodRanges(): array
    {
        $bounds = [
            'Morning' => [$this->period_morning_start, $this->period_morning_end],
            'Afternoon' => [$this->period_afternoon_start, $this->period_afternoon_end],
            'Evening' => [$this->period_evening_start, $this->period_evening_end],
        ];

        return collect($bounds)->map(fn ($range, $label) => [
            'label' => $label,
            'from' => $this->friendlyTime($this->toMinutes($range[0])),
            'to' => $this->friendlyTime($this->toMinutes($range[1])),
            'has_slots' => true,
        ])->values()->all();
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
