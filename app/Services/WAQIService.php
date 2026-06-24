<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;

class WAQIService
{
    private Client $client;

    private string $baseUrl;

    private ?string $token;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->baseUrl = config('airsense.waqi.base_url');
        $this->token = config('airsense.waqi.token');
    }

    /**
     * @return array{status: string, data?: array<string, mixed>, message?: string}
     */
    public function fetchStation(string $stationSlug): array
    {
        if (empty($this->token)) {
            return [
                'status' => 'error',
                'message' => 'WAQI API token is not configured',
            ];
        }

        $url = $this->baseUrl . $stationSlug . '/?token=' . $this->token;

        try {
            $response = $this->client->get($url, [
                'timeout' => config('airsense.waqi.timeout', 10),
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [
                'status' => 'error',
                'message' => 'Invalid JSON response from WAQI',
            ];
        } catch (ConnectException $e) {
            Log::warning('WAQI network error', ['station' => $stationSlug, 'error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Network error',
            ];
        } catch (ClientException $e) {
            Log::warning('WAQI API error', ['station' => $stationSlug, 'error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'API error',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function parseReadingData(array $payload): ?array
    {
        if (($payload['status'] ?? '') !== 'ok') {
            return null;
        }

        $data = $payload['data'] ?? [];
        $iaqi = $data['iaqi'] ?? [];

        return [
            'aqi' => (int) ($data['aqi'] ?? 0),
            'pm25' => $this->pollutantValue($iaqi, 'pm25'),
            'pm10' => $this->pollutantValue($iaqi, 'pm10'),
            'no2' => $this->pollutantValue($iaqi, 'no2'),
            'o3' => $this->pollutantValue($iaqi, 'o3'),
            'co' => $this->pollutantValue($iaqi, 'co'),
            'temperature' => $this->pollutantValue($iaqi, 't'),
            'humidity' => $this->pollutantValue($iaqi, 'h'),
            'wind_speed' => $this->pollutantValue($iaqi, 'w'),
            'fetched_at' => isset($data['time']['s'])
                ? \Carbon\Carbon::parse($data['time']['s'])
                : now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $iaqi
     */
    private function pollutantValue(array $iaqi, string $key): ?float
    {
        if (! isset($iaqi[$key]['v'])) {
            return null;
        }

        return (float) $iaqi[$key]['v'];
    }
}
