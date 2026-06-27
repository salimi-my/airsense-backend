<?php

namespace Tests\Unit;

use App\Models\Reading;
use App\Models\Station;
use App\Services\WAQIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FetchAQIDataTest extends TestCase
{
    use RefreshDatabase;

    private function seedStation(string $name, string $slug): Station
    {
        return Station::query()->create([
            'name' => $name,
            'city' => 'Kuala Lumpur',
            'lat' => 3.1,
            'lng' => 101.6,
            'waqi_slug' => $slug,
            'is_active' => true,
        ]);
    }

    public function test_command_upserts_readings_for_all_stations(): void
    {
        $stations = collect([
            $this->seedStation('Petaling Jaya', 'petaling-jaya'),
            $this->seedStation('Kuala Lumpur', 'kuala-lumpur'),
            $this->seedStation('Shah Alam', 'shah-alam'),
            $this->seedStation('Klang', 'klang'),
            $this->seedStation('Putrajaya', 'putrajaya'),
        ]);

        $fetchedAt = now()->startOfHour();

        $this->mock(WAQIService::class, function ($mock) use ($fetchedAt) {
            $mock->shouldReceive('fetchStation')->times(10)->andReturn(['status' => 'ok']);
            $mock->shouldReceive('parseReadingData')->times(10)->andReturn([
                'aqi' => 85,
                'pm25' => 30,
                'pm10' => 45,
                'no2' => null,
                'o3' => null,
                'co' => null,
                'temperature' => null,
                'humidity' => null,
                'wind_speed' => null,
                'fetched_at' => $fetchedAt,
            ]);
        });

        Artisan::call('app:fetch-aqi-data');

        $this->assertSame(5, Reading::query()->count());

        Artisan::call('app:fetch-aqi-data');

        $this->assertSame(5, Reading::query()->count());
        $this->assertDatabaseHas('readings', [
            'station_id' => $stations->first()->id,
            'aqi' => 85,
        ]);
    }

    public function test_command_logs_warning_when_station_fetch_fails(): void
    {
        $this->seedStation('Petaling Jaya', 'petaling-jaya');
        $this->seedStation('Kuala Lumpur', 'kuala-lumpur');

        Log::shouldReceive('warning')->twice();

        $this->mock(WAQIService::class, function ($mock) {
            $mock->shouldReceive('fetchStation')->andReturn([
                'status' => 'error',
                'message' => 'Network error',
            ]);
            $mock->shouldReceive('parseReadingData')->andReturn(null);
        });

        Artisan::call('app:fetch-aqi-data');

        $this->assertSame(0, Reading::query()->count());
    }
}
