<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenPlayPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'open_play_room_id',
        'user_id',
        'current_status',
        'games_played',
        'wins',
        'losses',
        'available_since',
        'checked_in_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'available_since' => 'datetime',
            'checked_in_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(OpenPlayRoom::class, 'open_play_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(OpenPlayMatchPlayer::class);
    }
}
