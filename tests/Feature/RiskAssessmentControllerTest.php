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

class RiskAssessmentControllerTest extends TestCase
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

    private function createStation(): Station
    {
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
            'aqi' => 150,
            'pm25' => 55,
            'pm10' => 70,
            'fetched_at' => now(),
        ]);

        return $station;
    }

    public function test_assessment_validation_requires_age_group(): void
    {
        $user = $this->createUser();
        $station = $this->createStation();

        $response = $this->actingAs($user)->postJson('/api/assessments', [
            'station_id' => $station->id,
            'conditions' => ['none'],
            'activity' => 'light_outdoor',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['age_group']);
    }

    public function test_assessment_validation_rejects_invalid_condition(): void
    {
        $user = $this->createUser();
        $station = $this->createStation();

        $response = $this->actingAs($user)->postJson('/api/assessments', [
            'station_id' => $station->id,
            'age_group' => 'adult',
            'conditions' => ['invalid_condition'],
            'activity' => 'light_outdoor',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0']);
    }

    public function test_assessment_persists_record_with_valid_request(): void
    {
        $user = $this->createUser();
        $station = $this->createStation();

        $this->mock(AIRiskService::class, function ($mock) {
            $mock->shouldReceive('classify')->once()->andReturn([
                'risk' => 'Critical',
                'advice' => 'Avoid outdoor exposure.',
                'precautions' => ['Stay indoors'],
                'confidence' => 0.95,
                'used_fallback' => false,
            ]);
        });

        $response = $this->actingAs($user)->postJson('/api/assessments', [
            'station_id' => $station->id,
            'age_group' => 'elderly',
            'conditions' => ['asthma'],
            'activity' => 'strenuous_exercise',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.assessment.risk', 'Critical');

        $this->assertDatabaseHas('assessments', [
            'user_id' => $user->id,
            'station_id' => $station->id,
            'risk_level' => 'Critical',
        ]);

        $this->assertSame(1, Assessment::query()->count());
    }

    public function test_assessment_returns_503_when_ai_service_unavailable(): void
    {
        $user = $this->createUser();
        $station = $this->createStation();

        $this->mock(AIRiskService::class, function ($mock) {
            $mock->shouldReceive('classify')->once()->andReturn([
                'error' => 'AI service temporarily unavailable',
            ]);
        });

        $response = $this->actingAs($user)->postJson('/api/assessments', [
            'station_id' => $station->id,
            'age_group' => 'adult',
            'conditions' => ['none'],
            'activity' => 'light_outdoor',
        ]);

        $response->assertStatus(503);
    }
}
