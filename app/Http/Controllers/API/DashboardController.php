<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\Assessment;
use App\Models\Reading;
use App\Models\Station;
use App\Support\AQIHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends AppBaseController
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $user->load(['preferredStation.latestReading', 'role']);

        $preferredStation = $user->preferredStation;
        $preferredStationData = null;

        if ($preferredStation) {
            $preferredStationData = $this->formatStation($preferredStation);
        }

        $lastAssessment = Assessment::query()
            ->where('user_id', $user->id)
            ->with('station:id,name,city')
            ->latest('assessed_at')
            ->first();

        $assessmentCountWeek = Assessment::query()
            ->where('user_id', $user->id)
            ->where('assessed_at', '>=', now()->subWeek())
            ->count();

        $activeAlertsCount = Station::query()
            ->where('is_active', true)
            ->with('latestReading')
            ->get()
            ->filter(fn (Station $station) => ($station->latestReading?->aqi ?? 0) > 100)
            ->count();

        $stationsWithReadings = Station::query()
            ->where('is_active', true)
            ->with('latestReading')
            ->get()
            ->filter(fn (Station $station) => $station->latestReading !== null);

        $valleyAvgAqi = $stationsWithReadings->isEmpty()
            ? 0
            : (int) round($stationsWithReadings->avg(fn (Station $s) => $s->latestReading->aqi));

        $data = [
            'preferred_station' => $preferredStationData,
            'last_assessment' => $lastAssessment ? [
                'id' => $lastAssessment->id,
                'risk_level' => $lastAssessment->risk_level,
                'station_id' => $lastAssessment->station_id,
                'station_name' => $lastAssessment->station?->name,
                'confidence' => $lastAssessment->confidence,
                'assessed_at' => $lastAssessment->assessed_at?->toISOString(),
            ] : null,
            'assessment_count_week' => $assessmentCountWeek,
            'active_alerts_count' => $activeAlertsCount,
            'valley_avg_aqi' => $valleyAvgAqi,
        ];

        if ($user->hasRole('admin')) {
            $staleHours = config('airsense.stale_reading_hours', 2);

            $staleStationsCount = Station::query()
                ->where('is_active', true)
                ->with('latestReading')
                ->get()
                ->filter(function (Station $station) use ($staleHours) {
                    $reading = $station->latestReading;

                    if (! $reading) {
                        return true;
                    }

                    return AQIHelper::isStale($reading->fetched_at, $staleHours);
                })
                ->count();

            $lastFetch = Reading::query()->max('fetched_at');

            $data['admin'] = [
                'assessments_today' => Assessment::query()
                    ->whereDate('assessed_at', today())
                    ->count(),
                'stale_stations_count' => $staleStationsCount,
                'last_fetch_at' => $lastFetch
                    ? \Carbon\Carbon::parse($lastFetch)->toISOString()
                    : null,
            ];
        }

        return $this->sendResponse($data, 'Dashboard data retrieved successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStation(Station $station): array
    {
        $reading = $station->latestReading;
        $aqi = (int) ($reading?->aqi ?? 0);

        return [
            'id' => $station->id,
            'name' => $station->name,
            'city' => $station->city,
            'lat' => $station->lat,
            'lng' => $station->lng,
            'waqi_slug' => $station->waqi_slug,
            'latest_reading' => $reading ? [
                'aqi' => $reading->aqi,
                'pm25' => $reading->pm25,
                'pm10' => $reading->pm10,
                'no2' => $reading->no2,
                'o3' => $reading->o3,
                'co' => $reading->co,
                'temperature' => $reading->temperature,
                'humidity' => $reading->humidity,
                'wind_speed' => $reading->wind_speed,
                'fetched_at' => $reading->fetched_at?->toISOString(),
                'category' => AQIHelper::getCategory($aqi),
                'color_class' => AQIHelper::getColorClass($aqi),
                'hex_color' => AQIHelper::getHexColor($aqi),
                'stale' => AQIHelper::isStale($reading->fetched_at, config('airsense.stale_reading_hours', 2)),
            ] : null,
        ];
    }
}
