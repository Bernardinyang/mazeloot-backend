<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upload Quotas
    |--------------------------------------------------------------------------
    |
    | Configure upload quotas per domain and per user
    | Values are in bytes
    |
    */

    'upload' => [
        'per_domain' => [
            // 'memora' => 1073741824, // 1GB
        ],
        'per_user' => [
            'default' => 524288000, // 500MB default
        ],
    ],
];
