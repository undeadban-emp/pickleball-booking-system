<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRallyEvent extends Model
{
    protected $fillable = [
        'match_game_id',
        'sequence',
        'event_type',
        'acting_team',
        'team1_score_after',
        'team2_score_after',
        'serving_team_after',
        'server_position_after',
        'server_number_after',
        'player_positions_after',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'player_positions_after' => 'array',
            'meta' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(MatchGame::class, 'match_game_id');
    }
}
