<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationCachedReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_index_returns_cached_reading_when_live_fetch_unavailable(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'user'],
            ['description' => 'Test role'],
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);

        $station = Station::query()->create([
            'name' => 'Petaling Jaya',
            'city' => 'Petaling Jaya',
            'lat' => 3.1,
            'lng' => 101.6,
            'waqi_slug' => 'petaling-jaya',
            'is_active' => true,
        ]);

        Reading::query()->create([
            'station_id' => $station->id,
            'aqi' => 92,
            'pm25' => 32,
            'pm10' => 48,
            'fetched_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/stations');

        $response->assertOk()
            ->assertJsonPath('data.stations.0.latest_reading.aqi', 92);
    }
}
