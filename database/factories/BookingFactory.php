<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'booking_code' => 'PB-'.Str::upper(Str::random(8)),
            'user_id' => User::factory(),
            'court_id' => Court::factory(),
            'status' => 'confirmed',
            'total_price' => 300,
            'receipt_token' => Str::random(40),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => ['status' => 'pending_payment']);
    }
}
