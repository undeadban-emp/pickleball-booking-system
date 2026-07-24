<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Court;
use App\Models\OpenPlayRoom;
use App\Models\OpenPlayRoomCourt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenPlayRoomCourt>
 */
class OpenPlayRoomCourtFactory extends Factory
{
    protected $model = OpenPlayRoomCourt::class;

    public function definition(): array
    {
        return [
            'open_play_room_id' => OpenPlayRoom::factory(),
            'court_id' => Court::factory(),
            'booking_id' => Booking::factory(),
        ];
    }
}
