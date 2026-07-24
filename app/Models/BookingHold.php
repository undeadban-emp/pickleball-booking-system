<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHold extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'from_court_id',
        'from_slot_date',
        'from_start_time',
        'from_end_time',
        'previous_status',
        'reason',
        'held_by',
        'created_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'from_slot_date' => 'date',
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
