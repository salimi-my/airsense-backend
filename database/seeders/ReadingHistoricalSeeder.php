<?php

namespace Database\Seeders;

use App\Models\Reading;
use App\Models\Station;
use Illuminate\Database\Seeder;

class ReadingHistoricalSeeder extends Seeder
{
    public function run(): void
    {
        $stations = Station::query()->where('is_active', true)->get();

        foreach ($stations as $index => $station) {
            $baseAqi = [45, 72, 95, 118, 88][$index % 5];
            $now = now()->startOfHour();

            for ($hour = 167; $hour >= 0; $hour--) {
                $fetchedAt = $now->copy()->subHours($hour);
                $variance = random_int(-15, 15);
                $aqi = max(10, min(350, $baseAqi + $variance + (int) (sin($hour / 12) * 10)));

                Reading::query()->create([
                    'station_id' => $station->id,
                    'aqi' => $aqi,
                    'pm25' => round($aqi * 0.4 + random_int(0, 5), 2),
                    'pm10' => round($aqi * 0.6 + random_int(0, 8), 2),
                    'no2' => round(random_int(5, 40) + ($aqi / 10), 2),
                    'o3' => round(random_int(10, 50), 2),
                    'co' => round(random_int(1, 10), 2),
                    'temperature' => round(28 + random_int(-3, 3) + sin($hour / 24) * 2, 1),
                    'humidity' => round(65 + random_int(-10, 10), 1),
                    'wind_speed' => round(random_int(3, 20), 1),
                    'fetched_at' => $fetchedAt,
                ]);
            }
        }
    }
}
