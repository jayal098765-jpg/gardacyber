<?php

return [
    'allow_any_public' => env('SCANNER_ALLOW_ANY_PUBLIC', true),
    'allowed_domains' => array_values(array_filter(array_map('trim', explode(',', env('SCANNER_ALLOWED_DOMAINS', ''))))),
];
