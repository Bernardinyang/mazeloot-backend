<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Upload Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default upload provider that will be used.
    | Supported: "local", "s3", "r2", "cloudinary"
    |
    */

    'default_provider' => env('UPLOAD_PROVIDER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each upload provider.
    |
    */

    'providers' => [
        'local' => [
            'disk' => env('FILESYSTEM_DISK', 'local'),
        ],

        's3' => [
            'disk' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

        'r2' => [
            'disk' => 'r2',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'url' => env('R2_URL'),
        ],

        'cloudinary' => [
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET'),
            'secure' => env('CLOUDINARY_SECURE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Maximum file size and other upload constraints
    |
    */

    'max_size' => env('UPLOAD_MAX_SIZE', 262144000), // 250MB in bytes (default, can be overridden via env)

    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'video/mp4',
        'video/webm',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota Configuration
    |--------------------------------------------------------------------------
    |
    | Upload quota settings per domain/user
    |
    */

    'quota' => [
        'per_domain' => [
            // 'memora' => 1073741824, // 1GB in bytes
        ],
        'per_user' => [
            // Default user quota in bytes
            'default' => 524288000, // 500MB
        ],
    ],
];
