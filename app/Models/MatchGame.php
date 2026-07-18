<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchGame extends Model
{
    protected $fillable = [
        'match_id',
        'game_number',
        'team1_score',
        'team2_score',
        'serving_team',
        'server_position',
        'server_number',
        'starting_serving_team',
        'starting_server_position',
        'starting_server_number',
        'starting_player_positions',
        'team1_timeouts_used',
        'team2_timeouts_used',
        'status',
        'winner_team',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'starting_player_positions' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function rallyEvents(): HasMany
    {
        return $this->hasMany(MatchRallyEvent::class)->orderBy('sequence');
    }

    public function scoreFor(int $team): int
    {
        return $team === 1 ? $this->team1_score : $this->team2_score;
    }

    public function timeoutsUsedFor(int $team): int
    {
        return $team === 1 ? $this->team1_timeouts_used : $this->team2_timeouts_used;
    }
}
