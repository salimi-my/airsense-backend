<?php

namespace App\Support;

class AQIHelper
{
    public static function getCategory(int $aqi): string
    {
        return match (true) {
            $aqi <= 50 => 'Good',
            $aqi <= 100 => 'Moderate',
            $aqi <= 200 => 'Unhealthy',
            $aqi <= 300 => 'Very Unhealthy',
            default => 'Hazardous',
        };
    }

    public static function getColorClass(int $aqi): string
    {
        return match (true) {
            $aqi <= 50 => 'good',
            $aqi <= 100 => 'moderate',
            $aqi <= 200 => 'unhealthy',
            $aqi <= 300 => 'very-unhealthy',
            default => 'hazardous',
        };
    }

    public static function getHexColor(int $aqi): string
    {
        return match (self::getColorClass($aqi)) {
            'good' => '#22c55e',
            'moderate' => '#eab308',
            'unhealthy' => '#f97316',
            'very-unhealthy' => '#ef4444',
            'hazardous' => '#a855f7',
        };
    }

    /**
     * @return array{risk: string, advice: string, precautions: array<int, string>, confidence: float}
     */
    public static function fallbackAdvisory(int $aqi, string $ageGroup = 'adult', array $conditions = ['none']): array
    {
        $sensitive = $ageGroup === 'child' || $ageGroup === 'elderly'
            || ! in_array('none', $conditions, true);

        $risk = match (true) {
            $aqi <= 50 => 'Low',
            $aqi <= 100 => $sensitive ? 'Moderate' : 'Low',
            $aqi <= 150 => $sensitive ? 'High' : 'Moderate',
            $aqi <= 200 => 'High',
            default => 'Critical',
        };

        $adviceMap = [
            'Low' => 'Air quality is safe. Outdoor activities can proceed as normal.',
            'Moderate' => 'Sensitive individuals (elderly, children, asthma) should limit prolonged outdoor exposure.',
            'High' => 'Reduce outdoor activity. Wear a mask if going outside. Keep windows closed.',
            'Critical' => 'Avoid all outdoor activities. Stay indoors with air purification if available. Seek medical advice if symptoms appear.',
        ];

        $precautionsMap = [
            'Low' => ['No special precautions needed', 'Stay hydrated', 'Monitor local air quality updates'],
            'Moderate' => ['Limit strenuous outdoor activity', 'Sensitive groups should take extra care', 'Check updates before outdoor plans'],
            'High' => ['Wear N95 mask outdoors', 'Reduce outdoor exposure', 'Keep windows closed'],
            'Critical' => ['Stay indoors', 'Use air purification if available', 'Seek medical advice if symptomatic'],
        ];

        return [
            'risk' => $risk,
            'advice' => $adviceMap[$risk],
            'precautions' => $precautionsMap[$risk],
            'confidence' => 0.5,
        ];
    }

    public static function isStale(?\DateTimeInterface $fetchedAt, int $hours = 2): bool
    {
        if ($fetchedAt === null) {
            return true;
        }

        return $fetchedAt < now()->subHours($hours);
    }
}
