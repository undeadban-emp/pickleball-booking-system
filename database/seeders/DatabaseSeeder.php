<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kitchenline.app'],
            [
                'name' => 'Kitchen Line Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@kitchenline.app'],
            [
                'name' => 'Kitchen Line Staff',
                'password' => bcrypt('password'),
                'role' => 'staff',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'player@kitchenline.app'],
            [
                'name' => 'Demo Player',
                'password' => bcrypt('password'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ]
        );

        PaymentMethod::updateOrCreate(
            ['name' => 'GCash'],
            [
                'account_number' => '0917 000 0000',
                'account_name' => 'Kitchen Line',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        $this->call(CourtSeeder::class);
    }
}
