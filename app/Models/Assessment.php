<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    protected $fillable = [
        'user_id',
        'station_id',
        'age_group',
        'conditions',
        'activity',
        'risk_level',
        'advice',
        'precautions',
        'confidence',
        'used_fallback',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'precautions' => 'array',
            'confidence' => 'float',
            'used_fallback' => 'boolean',
            'assessed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
