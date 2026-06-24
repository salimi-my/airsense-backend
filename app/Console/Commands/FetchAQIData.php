<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Models\Station;
use App\Services\WAQIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchAQIData extends Command
{
    protected $signature = 'app:fetch-aqi-data';

    protected $description = 'Fetch latest AQI readings from WAQI for all active stations';

    public function handle(WAQIService $waqiService): int
    {
        $stations = Station::query()->where('is_active', true)->get();

        if ($stations->isEmpty()) {
            $this->warn('No active stations configured.');

            return self::SUCCESS;
        }

        foreach ($stations as $station) {
            $response = $waqiService->fetchStation($station->waqi_slug);
            $parsed = $waqiService->parseReadingData($response);

            if ($parsed === null) {
                Log::warning('WAQI fetch failed for station: ' . $station->waqi_slug);
                $this->warn("Failed to fetch: {$station->name}");

                continue;
            }

            Reading::query()->create([
                'station_id' => $station->id,
                ...$parsed,
            ]);

            $this->info("Updated {$station->name} — AQI {$parsed['aqi']}");
        }

        return self::SUCCESS;
    }
}
