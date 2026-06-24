<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_stations(): void
    {
        $role = Role::factory()->create(['name' => 'user']);
        $user = User::factory()->create(['role_id' => $role->id, 'email_verified_at' => now()]);

        $station = Station::query()->create([
            'name' => 'Test Station',
            'city' => 'Kuala Lumpur',
            'lat' => 3.14,
            'lng' => 101.68,
            'waqi_slug' => 'test-station',
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
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stations.0.name', 'Test Station');
    }
}
