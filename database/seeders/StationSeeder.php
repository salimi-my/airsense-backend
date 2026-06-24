<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        $stations = [
            ['name' => 'Petaling Jaya', 'city' => 'Petaling Jaya', 'lat' => 3.1073, 'lng' => 101.6067, 'waqi_slug' => 'petaling-jaya'],
            ['name' => 'Kuala Lumpur', 'city' => 'Kuala Lumpur', 'lat' => 3.1390, 'lng' => 101.6869, 'waqi_slug' => 'kuala-lumpur'],
            ['name' => 'Shah Alam', 'city' => 'Shah Alam', 'lat' => 3.0733, 'lng' => 101.5185, 'waqi_slug' => 'shah-alam'],
            ['name' => 'Klang', 'city' => 'Klang', 'lat' => 3.0449, 'lng' => 101.4451, 'waqi_slug' => 'klang'],
            ['name' => 'Putrajaya', 'city' => 'Putrajaya', 'lat' => 2.9264, 'lng' => 101.6964, 'waqi_slug' => 'putrajaya'],
        ];

        foreach ($stations as $station) {
            Station::query()->updateOrCreate(
                ['waqi_slug' => $station['waqi_slug']],
                [...$station, 'is_active' => true]
            );
        }
    }
}
