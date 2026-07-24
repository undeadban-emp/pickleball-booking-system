<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenPlayRoomCourt extends Model
{
    use HasFactory;

    protected $fillable = [
        'open_play_room_id',
        'court_id',
        'booking_id',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(OpenPlayRoom::class, 'open_play_room_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
