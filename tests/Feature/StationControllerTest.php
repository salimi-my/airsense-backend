<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'user'],
            ['description' => 'Test role'],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_show_returns_404_for_missing_station(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/stations/9999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_index_returns_station_list_with_latest_reading(): void
    {
        $user = $this->createUser();

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
            'aqi' => 85,
            'pm25' => 30,
            'pm10' => 45,
            'fetched_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/stations');

        $response->assertOk()
            ->assertJsonPath('data.stations.0.name', 'Petaling Jaya')
            ->assertJsonPath('data.stations.0.latest_reading.aqi', 85);
    }
}
