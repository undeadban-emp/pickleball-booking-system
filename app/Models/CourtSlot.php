<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourtSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'court_id',
        'slot_date',
        'start_time',
        'end_time',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_slots');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
