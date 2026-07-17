<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\OperatingHours;
use App\Support\SlotGenerator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hours = OperatingHours::current();

        $courts = [
            ['name' => 'Court A, the show court', 'location' => 'Riverside strip, near the entrance', 'default_price' => 300],
            ['name' => 'Court B', 'location' => 'Riverside strip', 'default_price' => 300],
            ['name' => 'Court C', 'location' => 'Riverside strip, shaded side', 'default_price' => 250],
        ];

        foreach ($courts as $courtData) {
            $court = Court::updateOrCreate(
                ['name' => $courtData['name']],
                [
                    'location' => $courtData['location'],
                    'default_price' => $courtData['default_price'],
                    'status' => 'active',
                    'is_active' => true,
                ]
            );

            SlotGenerator::generate($court, $hours);
        }
    }
}
