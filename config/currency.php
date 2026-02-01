<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Exchange Rate Provider
    |--------------------------------------------------------------------------
    |
    | frankfurter: Free, no API key, ECB reference rates (may lack some African currencies)
    | exchangerate: open.er-api.com, no key, 165+ currencies, rate-limited (cache 12h+)
    | fallback: Config rates used when API fails or currency unsupported
    |
    */

    'provider' => env('EXCHANGE_RATE_PROVIDER', 'exchangerate'),

    'frankfurter_url' => 'https://api.frankfurter.dev/v1/latest',
    'exchangerate_url' => 'https://open.er-api.com/v6/latest',

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | Configuration for supported currencies including decimal places and symbols
    |
    */

    'currencies' => [
        'USD' => ['decimals' => 2, 'symbol' => '$'],
        'EUR' => ['decimals' => 2, 'symbol' => '€'],
        'GBP' => ['decimals' => 2, 'symbol' => '£'],
        'NGN' => ['decimals' => 2, 'symbol' => '₦'],
        'ZAR' => ['decimals' => 2, 'symbol' => 'R'],
        'KES' => ['decimals' => 2, 'symbol' => 'KSh'],
        'GHS' => ['decimals' => 2, 'symbol' => '₵'],
        'JPY' => ['decimals' => 0, 'symbol' => '¥'],
        'CAD' => ['decimals' => 2, 'symbol' => 'C$'],
        'AUD' => ['decimals' => 2, 'symbol' => 'A$'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Exchange Rates (used when API fails or currency unsupported)
    |--------------------------------------------------------------------------
    |
    | Format: 'FROM_TO' => rate (USD as base)
    |
    */

    'exchange_rates' => [
        // USD as base
        'USD_EUR' => 0.92,
        'USD_GBP' => 0.79,
        'USD_NGN' => 1450.00,
        'USD_ZAR' => 18.50,
        'USD_KES' => 130.00,
        'USD_GHS' => 12.00,
        'USD_JPY' => 150.00,
        'USD_CAD' => 1.36,
        'USD_AUD' => 1.53,
    ],
];
