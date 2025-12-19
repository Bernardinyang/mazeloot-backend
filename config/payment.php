<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment provider that will be used.
    | Supported: "stripe", "paypal", "paystack", "flutterwave"
    |
    */

    'default_provider' => env('PAYMENT_PROVIDER', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each payment provider.
    |
    */

    'providers' => [
        'stripe' => [
            'public_key' => env('STRIPE_KEY'),
            'secret_key' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],

        'paystack' => [
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        ],

        'flutterwave' => [
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider MemoraSelection Rules
    |--------------------------------------------------------------------------
    |
    | Automatic provider selection based on currency or country
    |
    */

    'provider_selection' => [
        'by_currency' => [
            'ngn' => 'paystack', // Nigerian Naira -> Paystack
            'ghs' => 'paystack', // Ghanaian Cedis -> Paystack
            'zar' => 'paystack', // South African Rand -> Paystack
            'kes' => 'paystack', // Kenyan Shilling -> Paystack
        ],
        'by_country' => [
            'NG' => 'paystack', // Nigeria -> Paystack
            'GH' => 'paystack', // Ghana -> Paystack
            'ZA' => 'paystack', // South Africa -> Paystack
            'KE' => 'paystack', // Kenya -> Paystack
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | Idempotency key caching duration
    |
    */

    'idempotency_cache_ttl' => 86400, // 24 hours in seconds
];
