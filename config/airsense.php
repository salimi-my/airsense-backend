<?php

return [
    'waqi' => [
        'base_url' => 'https://api.waqi.info/feed/',
        'token' => env('WAQI_API_TOKEN'),
        'timeout' => 10,
    ],

    'ai_service' => [
        'url' => rtrim(env('AI_SERVICE_URL', ''), '/'),
        'timeout' => 15,
    ],

    'stale_reading_hours' => 2,

    'max_station_distance_km' => 50,
];
