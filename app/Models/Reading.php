<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
