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

class RiskAssessmentPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_assess_pipeline_persists_assessment_and_returns_ai_output(): void
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
            'aqi' => 150,
            'pm25' => 55,
            'pm10' => 70,
            'fetched_at' => now(),
        ]);

        $this->mock(AIRiskService::class, function ($mock) {
            $mock->shouldReceive('classify')->once()->andReturn([
                'risk' => 'Critical',
                'advice' => 'Avoid all outdoor exposure.',
                'precautions' => ['Stay indoors'],
                'confidence' => 0.94,
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
            ->assertJsonPath('data.assessment.risk', 'Critical')
            ->assertJsonPath('data.assessment.advice', 'Avoid all outdoor exposure.');

        $this->assertSame(1, Assessment::query()->count());
    }
}
