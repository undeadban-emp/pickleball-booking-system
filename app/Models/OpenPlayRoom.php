<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenPlayRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_user_id',
        'title',
        'session_date',
        'start_time',
        'skill_level',
        'max_players',
        'match_format',
        'points_target',
        'timer_minutes',
        'visibility',
        'join_code',
        'status',
        'current_round_number',
        'started_at',
        'last_activity_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function roomCourts(): HasMany
    {
        return $this->hasMany(OpenPlayRoomCourt::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(OpenPlayPlayer::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(OpenPlayMatch::class);
    }

    public function isFull(): bool
    {
        return $this->players()->count() >= $this->max_players;
    }
}
