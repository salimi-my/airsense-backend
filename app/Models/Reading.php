<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Reading extends Model
{
    protected $fillable = [
        'station_id',
        'aqi',
        'pm25',
        'pm10',
        'no2',
        'o3',
        'co',
        'temperature',
        'humidity',
        'wind_speed',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'aqi' => 'integer',
            'pm25' => 'float',
            'pm10' => 'float',
            'no2' => 'float',
            'o3' => 'float',
            'co' => 'float',
            'temperature' => 'float',
            'humidity' => 'float',
            'wind_speed' => 'float',
            'fetched_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')
            ->orderByDesc('fetched_at')
            ->orderByDesc('id');
    }

    /**
     * When multiple rows share the same WAQI observation hour, keep the most recently ingested.
     *
     * @param  Collection<int, Reading>  $readings
     * @return Collection<int, Reading>
     */
    public static function dedupeByFetchedAt(Collection $readings): Collection
    {
        return $readings
            ->groupBy(fn (Reading $reading) => $reading->fetched_at?->toISOString() ?? (string) $reading->id)
            ->map(fn (Collection $group) => $group->sortByDesc('created_at')->first())
            ->sortBy(fn (Reading $reading) => $reading->fetched_at)
            ->values();
    }
}
