<?php

/**
 * Payment config: test vs live.
 * - PAYMENT_MODE=test (or APP_ENV != production): use test/sandbox keys; providers mock API calls (no live keys sent).
 * - PAYMENT_MODE=live: use live keys only; real API calls.
 * Per-provider TEST_MODE env (e.g. STRIPE_TEST_MODE) can force test for that provider.
 */
$paymentMode = env('PAYMENT_MODE', env('APP_ENV') === 'production' ? 'live' : 'test');
$useTestKeys = $paymentMode === 'test';

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
    | Payment Mode (test | live)
    |--------------------------------------------------------------------------
    |
    | Use PAYMENT_MODE=test for test credentials, PAYMENT_MODE=live for live.
    | When unset, defaults to test in local/dev, live in production.
    |
    */

    'mode' => $paymentMode,

    /*
    |--------------------------------------------------------------------------
    | Payment Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Uses separate test/live keys per provider:
    | - Stripe: STRIPE_TEST_* / STRIPE_LIVE_*
    | - PayPal: PAYPAL_TEST_* / PAYPAL_LIVE_*
    | - Paystack: PAYSTACK_TEST_* / PAYSTACK_LIVE_*
    | - Flutterwave: FLUTTERWAVE_TEST_* / FLUTTERWAVE_LIVE_*
    |
    */

    'providers' => [
        'stripe' => [
            'test_mode' => $useTestKeys
                || filter_var(env('STRIPE_TEST_MODE'), FILTER_VALIDATE_BOOLEAN)
                || (env('STRIPE_TEST_MODE') === null && str_starts_with((string) (env('STRIPE_TEST_SECRET') ?? env('STRIPE_LIVE_SECRET') ?? ''), 'sk_test_')),
            'public_key' => $useTestKeys
                ? env('STRIPE_TEST_PUBLIC_KEY')
                : env('STRIPE_LIVE_PUBLIC_KEY'),
            'secret_key' => $useTestKeys
                ? env('STRIPE_TEST_SECRET')
                : env('STRIPE_LIVE_SECRET'),
            'webhook_secret' => $useTestKeys
                ? env('STRIPE_TEST_WEBHOOK_SECRET')
                : env('STRIPE_LIVE_WEBHOOK_SECRET'),
        ],

        'paypal' => [
            'test_mode' => $useTestKeys
                || filter_var(env('PAYPAL_TEST_MODE'), FILTER_VALIDATE_BOOLEAN),
            'base_url' => $useTestKeys
                ? 'https://api-m.sandbox.paypal.com'
                : 'https://api-m.paypal.com',
            'client_id' => $useTestKeys
                ? env('PAYPAL_TEST_CLIENT_ID')
                : env('PAYPAL_LIVE_CLIENT_ID'),
            'client_secret' => $useTestKeys
                ? env('PAYPAL_TEST_CLIENT_SECRET')
                : env('PAYPAL_LIVE_CLIENT_SECRET'),
            'mode' => $useTestKeys ? 'sandbox' : 'live',
            'webhook_id' => $useTestKeys
                ? env('PAYPAL_TEST_WEBHOOK_ID')
                : env('PAYPAL_LIVE_WEBHOOK_ID'),
        ],

        'paystack' => [
            'test_mode' => $useTestKeys
                || filter_var(env('PAYSTACK_TEST_MODE'), FILTER_VALIDATE_BOOLEAN),
            'public_key' => $useTestKeys
                ? env('PAYSTACK_TEST_PUBLIC_KEY')
                : env('PAYSTACK_LIVE_PUBLIC_KEY'),
            'secret_key' => $useTestKeys
                ? env('PAYSTACK_TEST_SECRET_KEY')
                : env('PAYSTACK_LIVE_SECRET_KEY'),
            'webhook_secret' => $useTestKeys
                ? env('PAYSTACK_TEST_WEBHOOK_SECRET')
                : env('PAYSTACK_LIVE_WEBHOOK_SECRET'),
        ],

        'flutterwave' => (function () use ($useTestKeys) {
            $fwTestMode = $useTestKeys
                || filter_var(env('FLUTTERWAVE_TEST_MODE'), FILTER_VALIDATE_BOOLEAN)
                || (env('FLUTTERWAVE_TEST_MODE') === null && str_starts_with((string) (env('FLUTTERWAVE_TEST_SECRET_KEY') ?? env('FLUTTERWAVE_LIVE_SECRET_KEY') ?? ''), 'FLWSECK_TEST'));

            return [
                'test_mode' => $fwTestMode,
                'base_url' => 'https://api.flutterwave.com/v3',
                'public_key' => $fwTestMode
                    ? env('FLUTTERWAVE_TEST_PUBLIC_KEY')
                    : env('FLUTTERWAVE_LIVE_PUBLIC_KEY'),
                'secret_key' => $fwTestMode
                    ? env('FLUTTERWAVE_TEST_SECRET_KEY')
                    : env('FLUTTERWAVE_LIVE_SECRET_KEY'),
                'client_id' => $fwTestMode
                    ? env('FLUTTERWAVE_TEST_CLIENT_ID')
                    : env('FLUTTERWAVE_LIVE_CLIENT_ID'),
                'client_secret' => $fwTestMode
                    ? env('FLUTTERWAVE_TEST_CLIENT_SECRET')
                    : env('FLUTTERWAVE_LIVE_CLIENT_SECRET'),
                'secret_hash' => $fwTestMode
                    ? env('FLUTTERWAVE_TEST_SECRET_HASH')
                    : env('FLUTTERWAVE_LIVE_SECRET_HASH'),
            ];
        })(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider MemoraSelection Rules
    |--------------------------------------------------------------------------
    |
    | Automatic provider selection based on currency or country
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Checkout Provider & Currency Support
    |--------------------------------------------------------------------------
    |
    | Which providers are enabled for subscription checkout and their currencies.
    | Provider is enabled if it has non-empty secret/public keys configured.
    |
    */

    'checkout_providers' => [
        'stripe' => [
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
        ],
        'paypal' => [
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
        ],
        'paystack' => [
            'currencies' => ['NGN', 'GHS', 'ZAR', 'KES'],
        ],
        'flutterwave' => [
            'currencies' => ['NGN', 'GHS', 'ZAR', 'KES', 'USD'],
        ],
    ],

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
