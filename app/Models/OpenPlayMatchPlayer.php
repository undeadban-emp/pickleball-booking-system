<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenPlayMatchPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'open_play_match_id',
        'open_play_player_id',
        'user_id',
        'team',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(OpenPlayMatch::class, 'open_play_match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(OpenPlayPlayer::class, 'open_play_player_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
