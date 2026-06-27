<?php

namespace Tests\Unit;

use App\Services\WAQIService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WAQIServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('airsense.waqi.token', 'test-token');
    }

    private function serviceWithResponses(array $responses): WAQIService
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new WAQIService($client);
    }

    public function test_fetch_station_returns_valid_response(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/waqi_moderate.json')),
            true
        );

        $service = $this->serviceWithResponses([
            new Response(200, [], json_encode($fixture)),
        ]);

        $result = $service->fetchStation('petaling-jaya');

        $this->assertSame('ok', $result['status']);
        $this->assertSame(85, $result['data']['aqi']);
    }

    public function test_fetch_station_handles_network_timeout(): void
    {
        $service = $this->serviceWithResponses([
            new ConnectException('Connection timed out', new Request('GET', 'test')),
        ]);

        $result = $service->fetchStation('petaling-jaya');

        $this->assertSame('error', $result['status']);
        $this->assertSame('Network error', $result['message']);
    }

    public function test_fetch_station_handles_client_error(): void
    {
        $service = $this->serviceWithResponses([
            new Response(404, [], json_encode(['status' => 'error'])),
        ]);

        $result = $service->fetchStation('petaling-jaya');

        $this->assertSame('error', $result['status']);
        $this->assertSame('API error', $result['message']);
    }
}
