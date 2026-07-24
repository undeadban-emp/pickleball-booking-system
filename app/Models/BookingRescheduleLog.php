<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRescheduleLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'from_court_id',
        'from_slot_date',
        'from_start_time',
        'from_end_time',
        'to_court_id',
        'to_slot_date',
        'to_start_time',
        'to_end_time',
        'changed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'from_slot_date' => 'date',
            'to_slot_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function fromCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'from_court_id');
    }

    public function toCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'to_court_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
