<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourtSlot>
 */
class CourtSlotFactory extends Factory
{
    protected $model = CourtSlot::class;

    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'slot_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'price' => 300,
            'status' => 'available',
        ];
    }
}
