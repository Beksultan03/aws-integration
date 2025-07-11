<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'], // Allow all methods or specify ['GET', 'POST']

    'allowed_origins' => env('ALLOWED_ORIGINS') ? explode(',', env('ALLOWED_ORIGINS')) : [],
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Allow all headers

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
