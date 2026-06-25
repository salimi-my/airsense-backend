<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\Reading;
use App\Models\Station;
use App\Support\AQIHelper;
use App\Support\GeoHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationController extends AppBaseController
{
    public function index(): JsonResponse
    {
        $stations = Station::query()
            ->where('is_active', true)
            ->with('latestReading')
            ->orderBy('name')
            ->get()
            ->map(fn (Station $station) => $this->formatStation($station));

        $lastUpdated = $stations
            ->pluck('latest_reading.created_at')
            ->filter()
            ->max();

        $latestObservation = $stations
            ->pluck('latest_reading.fetched_at')
            ->filter()
            ->max();

        return $this->sendResponse([
            'stations' => $stations,
            'last_updated' => $lastUpdated,
            'stale' => AQIHelper::isStale($latestObservation ? \Carbon\Carbon::parse($latestObservation) : null, config('airsense.stale_reading_hours', 2)),
        ], 'Stations retrieved successfully');
    }

    public function show(int $id): JsonResponse
    {
        $station = Station::query()
            ->where('is_active', true)
            ->with('latestReading')
            ->find($id);

        if (! $station) {
            return $this->sendError('Station not found', 404);
        }

        return $this->sendResponse(
            $this->formatStation($station),
            'Station retrieved successfully'
        );
    }

    public function readings(Request $request, int $id): JsonResponse
    {
        $station = Station::query()->where('is_active', true)->find($id);

        if (! $station) {
            return $this->sendError('Station not found', 404);
        }

        $days = min((int) $request->query('days', 7), 7);

        $readings = Reading::dedupeByFetchedAt(
            $station->readings()
                ->where('fetched_at', '>=', now()->subDays($days))
                ->latestFirst()
                ->get()
        )->map(fn ($reading) => [
                'id' => $reading->id,
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
            ]);

        return $this->sendResponse([
            'station' => $this->formatStation($station->load('latestReading')),
            'readings' => $readings,
        ], 'Readings retrieved successfully');
    }

    public function alerts(): JsonResponse
    {
        $stations = Station::query()
            ->where('is_active', true)
            ->with('latestReading')
            ->get()
            ->filter(fn (Station $station) => ($station->latestReading?->aqi ?? 0) > 100)
            ->map(fn (Station $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'aqi' => $station->latestReading?->aqi,
                'category' => AQIHelper::getCategory((int) $station->latestReading?->aqi),
                'color_class' => AQIHelper::getColorClass((int) $station->latestReading?->aqi),
                'message' => 'Sensitive groups should limit outdoor activity.',
            ])
            ->values();

        return $this->sendResponse([
            'alerts' => $stations,
            'has_alerts' => $stations->isNotEmpty(),
        ], 'Alerts retrieved successfully');
    }

    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        $stations = Station::query()
            ->where('is_active', true)
            ->with('latestReading')
            ->get();

        if ($stations->isEmpty()) {
            return $this->sendError('No active stations available', 404);
        }

        $nearest = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($stations as $station) {
            $distance = GeoHelper::distanceKm($lat, $lng, (float) $station->lat, (float) $station->lng);

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $station;
            }
        }

        $maxDistance = config('airsense.max_station_distance_km', 50);

        return $this->sendResponse([
            'station' => $this->formatStation($nearest),
            'distance_km' => $nearestDistance,
            'within_coverage' => $nearestDistance <= $maxDistance,
        ], 'Nearest station retrieved successfully');
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
                'created_at' => $reading->created_at?->toISOString(),
                'category' => AQIHelper::getCategory($aqi),
                'color_class' => AQIHelper::getColorClass($aqi),
                'hex_color' => AQIHelper::getHexColor($aqi),
                'stale' => AQIHelper::isStale($reading->fetched_at, config('airsense.stale_reading_hours', 2)),
            ] : null,
        ];
    }
}
