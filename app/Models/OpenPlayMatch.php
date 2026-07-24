<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenPlayMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'open_play_room_id',
        'court_id',
        'round_number',
        'status',
        'winner_team',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(OpenPlayRoom::class, 'open_play_room_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(OpenPlayMatchPlayer::class);
    }

    public function scopeTeam(Builder $query, int $team): Builder
    {
        return $query->whereHas('matchPlayers', fn ($q) => $q->where('team', $team));
    }
}
