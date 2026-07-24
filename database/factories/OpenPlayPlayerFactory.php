<?php

namespace Database\Factories;

use App\Models\OpenPlayPlayer;
use App\Models\OpenPlayRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenPlayPlayer>
 */
class OpenPlayPlayerFactory extends Factory
{
    protected $model = OpenPlayPlayer::class;

    public function definition(): array
    {
        return [
            'open_play_room_id' => OpenPlayRoom::factory(),
            'user_id' => User::factory(),
            'current_status' => 'waiting',
            'games_played' => 0,
            'wins' => 0,
            'losses' => 0,
            'available_since' => now(),
            // Checked in by default so matchmaking-focused tests (which
            // build players directly rather than through joinRoom/checkIn)
            // don't need to opt in separately just to be eligible.
            'checked_in_at' => now(),
        ];
    }
}
