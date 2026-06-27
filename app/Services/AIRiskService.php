<?php

namespace App\Services;

use App\Exceptions\AIServiceException;
use App\Support\AQIHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AIRiskService
{
    private Client $client;

    private string $baseUrl;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->baseUrl = config('airsense.ai_service.url', '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{risk?: string, advice?: string, precautions?: array<int, string>, confidence?: float, used_fallback?: bool, error?: string}
     */
    public function classify(array $payload): array
    {
        // Dev-only fallback when AI service URL is not configured.
        if (empty($this->baseUrl)) {
            return $this->withFallbackFlag(
                AQIHelper::fallbackAdvisory(
                    (int) $payload['aqi'],
                    $payload['age_group'] ?? 'adult',
                    $payload['conditions'] ?? ['none']
                )
            );
        }

        try {
            $response = $this->client->post($this->baseUrl . '/predict', [
                'json' => $this->mapToPredictPayload($payload),
                'timeout' => config('airsense.ai_service.timeout', 15),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            if ($response->getStatusCode() === 503) {
                return ['error' => 'AI service temporarily unavailable'];
            }

            $body = json_decode($response->getBody()->getContents(), true);

            if (! is_array($body) || empty($body['risk'])) {
                throw new AIServiceException('Invalid AI service response');
            }

            return [
                'risk' => $body['risk'],
                'advice' => $body['advice'] ?? '',
                'precautions' => $this->precautionsForRisk($body['risk']),
                'confidence' => (float) ($body['confidence'] ?? 0),
                'used_fallback' => false,
            ];
        } catch (GuzzleException | AIServiceException $e) {
            Log::warning('AI classify service unavailable', ['error' => $e->getMessage()]);

            return ['error' => 'AI service temporarily unavailable'];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mapToPredictPayload(array $payload): array
    {
        $conditions = $payload['conditions'] ?? ['none'];
        $primary = collect($conditions)->first(fn ($condition) => $condition !== 'none') ?? 'none';

        $conditionMap = [
            'none' => 'none',
            'asthma' => 'asthma',
            'heart_disease' => 'heart',
            'respiratory' => 'copd',
            'diabetes' => 'none',
        ];

        $activityMap = [
            'indoor' => 'rest',
            'light_outdoor' => 'light',
            'moderate_exercise' => 'moderate',
            'strenuous_exercise' => 'outdoor_exercise',
        ];

        return [
            'aqi' => (int) $payload['aqi'],
            'pm25' => (float) ($payload['pm25'] ?? 0),
            'age_group' => $payload['age_group'],
            'condition' => $conditionMap[$primary] ?? 'none',
            'activity' => $activityMap[$payload['activity'] ?? 'light_outdoor'] ?? 'light',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function precautionsForRisk(string $risk): array
    {
        return match ($risk) {
            'Low' => ['No special precautions needed', 'Stay hydrated', 'Monitor local air quality updates'],
            'Moderate' => ['Limit strenuous outdoor activity', 'Sensitive groups should take extra care', 'Check updates before outdoor plans'],
            'High' => ['Wear N95 mask outdoors', 'Reduce outdoor exposure', 'Keep windows closed'],
            'Critical' => ['Stay indoors', 'Use air purification if available', 'Seek medical advice if symptomatic'],
            default => [],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $readings
     * @return array{predicted_aqi: int, category: string, color_class: string, confidence: float, used_fallback: bool, disclaimer: string}
     */
    public function predictAqi(array $readings): array
    {
        $disclaimer = 'This is an AI-generated estimate and should not be used as the sole basis for health decisions.';

        if (count($readings) < 72) {
            return [
                'predicted_aqi' => 0,
                'category' => 'Unavailable',
                'color_class' => 'moderate',
                'confidence' => 0,
                'used_fallback' => true,
                'disclaimer' => $disclaimer,
                'message' => 'Prediction unavailable — insufficient historical data',
            ];
        }

        if (empty($this->baseUrl)) {
            $lastAqi = (int) ($readings[count($readings) - 1]['aqi'] ?? 0);

            return [
                'predicted_aqi' => $lastAqi,
                'category' => AQIHelper::getCategory($lastAqi),
                'color_class' => AQIHelper::getColorClass($lastAqi),
                'confidence' => 0.5,
                'used_fallback' => true,
                'disclaimer' => $disclaimer,
                'message' => 'AI service not configured. Showing latest reading as estimate.',
            ];
        }

        try {
            $response = $this->client->post($this->baseUrl . '/predict-aqi', [
                'json' => ['readings' => array_slice($readings, -72)],
                'timeout' => config('airsense.ai_service.timeout', 15),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (! is_array($body) || ! isset($body['predicted_aqi'])) {
                throw new AIServiceException('Invalid prediction response');
            }

            $predicted = (int) $body['predicted_aqi'];

            return [
                'predicted_aqi' => $predicted,
                'category' => AQIHelper::getCategory($predicted),
                'color_class' => AQIHelper::getColorClass($predicted),
                'confidence' => (float) ($body['confidence'] ?? 0),
                'used_fallback' => false,
                'disclaimer' => $disclaimer,
            ];
        } catch (GuzzleException | AIServiceException $e) {
            Log::warning('AI prediction service unavailable', ['error' => $e->getMessage()]);

            $recent = array_slice($readings, -24);
            $avg = (int) round(collect($recent)->avg('aqi'));

            return [
                'predicted_aqi' => $avg,
                'category' => AQIHelper::getCategory($avg),
                'color_class' => AQIHelper::getColorClass($avg),
                'confidence' => 0.4,
                'used_fallback' => true,
                'disclaimer' => $disclaimer,
                'message' => 'Personalized assessment temporarily unavailable. Showing trend-based estimate.',
            ];
        }
    }

    public function warmup(): void
    {
        if (empty($this->baseUrl)) {
            return;
        }

        try {
            $this->client->get($this->baseUrl . '/warmup', [
                'timeout' => 30,
            ]);
        } catch (GuzzleException $e) {
            try {
                $this->client->get($this->baseUrl . '/health', [
                    'timeout' => 30,
                ]);
            } catch (GuzzleException $healthException) {
                Log::info('AI warmup request failed', ['error' => $healthException->getMessage()]);
            }
        }
    }

    /**
     * @param  array{risk: string, advice: string, precautions: array<int, string>, confidence: float}  $result
     * @return array{risk: string, advice: string, precautions: array<int, string>, confidence: float, used_fallback: bool}
     */
    private function withFallbackFlag(array $result): array
    {
        return array_merge($result, ['used_fallback' => true]);
    }
}
