<?php

return [
    'provider' => env('AI_PROVIDER'),
    'base_url' => env('AI_BASE_URL'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
];
