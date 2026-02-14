<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/*', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_merge(
        [env('FRONTEND_URL')],
        [
            'https://mazeloott.vercel.app',
            'https://www.mazeloot.com',
            'https://mazeloot.com',
            'http://localhost:5173',
            'http://localhost:3000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:3000',
        ]
    ), fn ($value) => is_string($value) && $value !== '')),

    'allowed_origins_patterns' => [
        '#^https://.*\\.vercel\\.app$#',
        '#^https://.*\\.mazeloot\\.com$#',
    ],

    'allowed_headers' => [
    'Content-Type',
    'X-Requested-With',
    'Authorization',
    'Origin',
    'Accept',
],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
