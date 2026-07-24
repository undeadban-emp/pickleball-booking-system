<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        return [
            'name' => 'Court '.fake()->unique()->numberBetween(1, 999),
            'location' => fake()->streetName(),
            'is_active' => true,
            'default_price' => 300,
            'status' => 'active',
        ];
    }
}
