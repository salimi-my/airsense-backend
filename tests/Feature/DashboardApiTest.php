<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Reading;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use App\Services\AIRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $roleName = 'user'): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Test role'],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function createStation(string $name, float $lat, float $lng, int $aqi = 85): Station
    {
        $station = Station::query()->create([
            'name' => $name,
            'city' => 'Kuala Lumpur',
            'lat' => $lat,
            'lng' => $lng,
            'waqi_slug' => strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
        ]);

        Reading::query()->create([
            'station_id' => $station->id,
            'aqi' => $aqi,
            'pm25' => 30,
            'pm10' => 45,
            'fetched_at' => now(),
        ]);

        return $station;
    }

    public function test_dashboard_returns_user_stats(): void
    {
        $user = $this->createUser();
        $station = $this->createStation('Petaling Jaya', 3.1, 101.6);
        $user->update(['preferred_station_id' => $station->id]);

        Assessment::query()->create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'age_group' => 'adult',
            'conditions' => ['none'],
            'activity' => 'light_outdoor',
            'risk_level' => 'Moderate',
            'advice' => 'Test advice',
            'precautions' => ['Wear a mask'],
            'confidence' => 0.85,
            'used_fallback' => false,
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preferred_station.name', 'Petaling Jaya')
            ->assertJsonPath('data.last_assessment.risk_level', 'Moderate')
            ->assertJsonPath('data.assessment_count_week', 1)
            ->assertJsonMissingPath('data.admin');
    }

    public function test_dashboard_includes_admin_fields_for_admin(): void
    {
        $admin = $this->createUser('admin');
        $station = $this->createStation('Shah Alam', 3.05, 101.52, 150);

        $response = $this->actingAs($admin)->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.active_alerts_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'admin' => [
                        'assessments_today',
                        'stale_stations_count',
                        'last_fetch_at',
                    ],
                ],
            ]);
    }

    public function test_me_assessments_returns_only_current_user_rows(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $station = $this->createStation('Klang', 3.04, 101.45);

        Assessment::query()->create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'age_group' => 'adult',
            'conditions' => ['none'],
            'activity' => 'indoor',
            'risk_level' => 'Low',
            'advice' => 'Mine',
            'precautions' => [],
            'confidence' => 0.9,
            'used_fallback' => false,
            'assessed_at' => now(),
        ]);

        Assessment::query()->create([
            'user_id' => $other->id,
            'station_id' => $station->id,
            'age_group' => 'elderly',
            'conditions' => ['asthma'],
            'activity' => 'indoor',
            'risk_level' => 'High',
            'advice' => 'Other',
            'precautions' => [],
            'confidence' => 0.7,
            'used_fallback' => false,
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/me/assessments');

        $response->assertOk()
            ->assertJsonPath('data.data.0.risk_level', 'Low')
            ->assertJsonCount(1, 'data.data');
    }

    public function test_assessment_sets_user_id(): void
    {
        $user = $this->createUser();
        $station = $this->createStation('Cheras', 3.08, 101.75);

        $this->mock(AIRiskService::class, function ($mock) {
            $mock->shouldReceive('classify')->once()->andReturn([
                'risk' => 'Low',
                'advice' => 'Safe',
                'precautions' => ['Enjoy outdoors'],
                'confidence' => 0.92,
                'used_fallback' => false,
            ]);
        });

        $response = $this->actingAs($user)->postJson('/api/assessments', [
            'station_id' => $station->id,
            'age_group' => 'adult',
            'conditions' => ['none'],
            'activity' => 'light_outdoor',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('assessments', [
            'user_id' => $user->id,
            'station_id' => $station->id,
            'risk_level' => 'Low',
        ]);
    }

    public function test_user_can_update_preferred_station(): void
    {
        $user = $this->createUser();
        $station = $this->createStation('Putrajaya', 2.93, 101.69);

        $response = $this->actingAs($user)->patchJson('/api/users/update-profile', [
            'name' => $user->name,
            'preferred_station_id' => $station->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_station_id' => $station->id,
        ]);
    }

    public function test_nearby_station_returns_nearest_with_coverage_flag(): void
    {
        $user = $this->createUser();
        $near = $this->createStation('Near Station', 3.14, 101.68);
        $this->createStation('Far Station', 2.5, 100.0);

        $response = $this->actingAs($user)->getJson('/api/stations/nearby?lat=3.139&lng=101.687');

        $response->assertOk()
            ->assertJsonPath('data.station.id', $near->id)
            ->assertJsonPath('data.within_coverage', true);

        $this->assertLessThan(5, $response->json('data.distance_km'));
    }

    public function test_nearby_station_validates_coordinates(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/stations/nearby?lat=999&lng=101');

        $response->assertStatus(422);
    }
}
