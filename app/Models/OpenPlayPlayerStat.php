<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenPlayPlayerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_games',
        'total_wins',
        'total_losses',
        'win_rate',
        'rank',
        'last_played_at',
    ];

    protected function casts(): array
    {
        return [
            'win_rate' => 'decimal:2',
            'last_played_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Games played + win rate tiering. A player who reaches a games
     * threshold but not the win-rate bar for the next tier stays one tier
     * below (e.g. 25 games at 40% stays Beginner, not Intermediate).
     */
    public static function rankFor(int $games, float $winRate): string
    {
        if ($games < 5) {
            return 'Novice';
        }

        if ($games < 20) {
            return 'Beginner';
        }

        if ($games >= 50 && $winRate >= 55) {
            return 'Advanced';
        }

        if ($winRate >= 45) {
            return 'Intermediate';
        }

        return 'Beginner';
    }
}
