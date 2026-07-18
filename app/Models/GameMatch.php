<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scored match attached to a checked-in booking. Named GameMatch (not
 * "Match") because `match` is a reserved word in PHP 8 and can't be used as
 * a class name.
 */
class GameMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'booking_id',
        'match_type',
        'best_of',
        'games_to',
        'win_rule',
        'timeouts_per_game',
        'scoring_type',
        'serving_team_first',
        'event_name',
        'referee_name',
        'location',
        'email_results_to',
        'status',
        'winner_team',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(MatchGame::class, 'match_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSinglesMatch(): bool
    {
        return $this->match_type === 'singles';
    }

    public function gamesToWinMatch(): int
    {
        return intdiv($this->best_of, 2) + 1;
    }

    public function teamName(int $team): string
    {
        // Sort by slot (stable identity), not position (current court side —
        // rotates during play, would make the label reorder mid-match).
        return $this->players
            ->where('team', $team)
            ->sortBy('slot')
            ->pluck('name')
            ->implode(' / ');
    }
}
