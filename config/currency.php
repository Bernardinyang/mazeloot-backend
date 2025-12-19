<?php

return [
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
    | Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Cached exchange rates (updated periodically)
    | Format: 'FROM_TO' => rate
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
        // Add more rates as needed
    ],
];
