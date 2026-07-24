<?php

namespace Database\Factories;

use App\Models\OpenPlayRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenPlayRoom>
 */
class OpenPlayRoomFactory extends Factory
{
    protected $model = OpenPlayRoom::class;

    public function definition(): array
    {
        return [
            'host_user_id' => User::factory(),
            'title' => 'Open Play — '.fake()->words(2, true),
            'session_date' => now()->addDay()->toDateString(),
            'start_time' => '18:00:00',
            'skill_level' => 'any',
            'max_players' => 16,
            'match_format' => 'first_to',
            'visibility' => 'public',
            'status' => 'waiting',
            'current_round_number' => 0,
        ];
    }
}
