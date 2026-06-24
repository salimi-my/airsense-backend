<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use App\Models\Station;
use App\Services\AIRiskService;
use Illuminate\Http\JsonResponse;

class RiskAssessmentController extends AppBaseController
{
    public function __construct(private AIRiskService $aiRiskService)
    {
    }

    public function assess(StoreAssessmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = Station::query()
            ->where('is_active', true)
            ->with('latestReading')
            ->findOrFail($validated['station_id']);

        $reading = $station->latestReading;

        if (! $reading) {
            return $this->sendError('No air quality data available for this station', 422);
        }

        $aiResult = $this->aiRiskService->classify([
            'station_id' => $station->id,
            'aqi' => $reading->aqi,
            'pm25' => $reading->pm25 ?? 0,
            'pm10' => $reading->pm10 ?? 0,
            'age_group' => $validated['age_group'],
            'conditions' => $validated['conditions'],
            'activity' => $validated['activity'],
        ]);

        $assessment = Assessment::query()->create([
            'user_id' => auth()->id(),
            'station_id' => $station->id,
            'age_group' => $validated['age_group'],
            'conditions' => $validated['conditions'],
            'activity' => $validated['activity'],
            'risk_level' => $aiResult['risk'],
            'advice' => $aiResult['advice'],
            'precautions' => $aiResult['precautions'],
            'confidence' => $aiResult['confidence'],
            'used_fallback' => $aiResult['used_fallback'],
            'assessed_at' => now(),
        ]);

        return $this->sendResponse([
            'assessment' => [
                'id' => $assessment->id,
                'risk' => $aiResult['risk'],
                'advice' => $aiResult['advice'],
                'precautions' => $aiResult['precautions'],
                'confidence' => $aiResult['confidence'],
                'used_fallback' => $aiResult['used_fallback'],
                'current_aqi' => $reading->aqi,
                'category' => \App\Support\AQIHelper::getCategory((int) $reading->aqi),
                'station_name' => $station->name,
                'low_confidence' => $aiResult['confidence'] < 0.5,
            ],
        ], $aiResult['used_fallback']
            ? 'Personalized assessment temporarily unavailable. Showing general guidance.'
            : 'Risk assessment completed successfully');
    }

    public function predict(int $id): JsonResponse
    {
        $station = Station::query()->where('is_active', true)->find($id);

        if (! $station) {
            return $this->sendError('Station not found', 404);
        }

        $readings = $station->readings()
            ->orderBy('fetched_at')
            ->get()
            ->map(fn ($reading) => [
                'aqi' => $reading->aqi,
                'pm25' => $reading->pm25,
                'pm10' => $reading->pm10,
                'no2' => $reading->no2,
                'o3' => $reading->o3,
                'temperature' => $reading->temperature,
                'humidity' => $reading->humidity,
                'wind_speed' => $reading->wind_speed,
                'fetched_at' => $reading->fetched_at?->toISOString(),
            ])
            ->all();

        $prediction = $this->aiRiskService->predictAqi($readings);

        return $this->sendResponse([
            'station_id' => $station->id,
            'station_name' => $station->name,
            'prediction' => $prediction,
        ], 'Prediction retrieved successfully');
    }
}
