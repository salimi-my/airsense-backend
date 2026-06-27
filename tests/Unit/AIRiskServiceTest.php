<?php

namespace Tests\Unit;

use App\Services\AIRiskService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AIRiskServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('airsense.ai_service.url', 'http://ai.test');
    }

    private function serviceWithResponses(array $responses): AIRiskService
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new AIRiskService($client);
    }

    public function test_classify_returns_structured_response_for_valid_ai_payload(): void
    {
        $service = $this->serviceWithResponses([
            new Response(200, [], json_encode([
                'risk' => 'High',
                'advice' => 'Limit outdoor activity.',
                'confidence' => 0.92,
            ])),
        ]);

        $result = $service->classify([
            'aqi' => 120,
            'pm25' => 45,
            'pm10' => 60,
            'age_group' => 'adult',
            'conditions' => ['none'],
            'activity' => 'moderate_exercise',
        ]);

        $this->assertSame('High', $result['risk']);
        $this->assertSame('Limit outdoor activity.', $result['advice']);
        $this->assertSame(0.92, $result['confidence']);
        $this->assertFalse($result['used_fallback']);
    }

    public function test_classify_returns_error_when_ai_service_unavailable(): void
    {
        $service = $this->serviceWithResponses([
            new Response(503, [], json_encode(['detail' => 'Unavailable'])),
        ]);

        $result = $service->classify([
            'aqi' => 120,
            'pm25' => 45,
            'pm10' => 60,
            'age_group' => 'adult',
            'conditions' => ['none'],
            'activity' => 'moderate_exercise',
        ]);

        $this->assertSame('AI service temporarily unavailable', $result['error']);
    }
}
