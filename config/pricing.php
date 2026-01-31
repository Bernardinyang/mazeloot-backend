<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Memora Tier Pricing
    |--------------------------------------------------------------------------
    |
    | Fixed tier structure for Memora. Prices in cents (USD).
    | Storage limits in bytes.
    |
    */

    'tiers' => [
        'starter' => [
            'name' => 'Starter',
            'price_monthly' => 0,
            'price_annual' => 0,
            'storage_bytes' => 5 * 1024 * 1024 * 1024, // 5GB
            'project_limit' => 3,
            'collection_limit' => 2,
            'max_revisions' => 0,
            'watermark_limit' => 1,
            'preset_limit' => 1,
            'team_seats' => 1,
            'raw_file_limit' => 0,
            'features' => ['selection', 'collection'],
        ],
        'pro' => [
            'name' => 'Pro',
            'price_monthly' => 1500, // $15
            'price_annual' => 14400, // $144 ($12 x 12)
            'storage_bytes' => 100 * 1024 * 1024 * 1024, // 100GB
            'project_limit' => null,
            'collection_limit' => null,
            'max_revisions' => 5,
            'watermark_limit' => 3,
            'preset_limit' => 5,
            'team_seats' => 1,
            'raw_file_limit' => 0,
            'features' => ['selection', 'proofing', 'collection', 'custom_domain', 'remove_branding'],
        ],
        'studio' => [
            'name' => 'Studio',
            'price_monthly' => 3500, // $35
            'price_annual' => 33600, // $336 ($28 x 12)
            'storage_bytes' => 500 * 1024 * 1024 * 1024, // 500GB
            'project_limit' => null,
            'collection_limit' => null,
            'max_revisions' => 10,
            'watermark_limit' => null,
            'preset_limit' => null,
            'team_seats' => 1,
            'raw_file_limit' => null,
            'features' => ['selection', 'proofing', 'collection', 'raw_files', 'custom_domain', 'remove_branding', 'advanced_analytics'],
        ],
        'business' => [
            'name' => 'Business',
            'price_monthly' => 5900, // $59
            'price_annual' => 58800, // $588 ($49 x 12)
            'storage_bytes' => null, // unlimited (use soft cap 2TB in enforcement)
            'project_limit' => null,
            'collection_limit' => null,
            'max_revisions' => 20,
            'watermark_limit' => null,
            'preset_limit' => null,
            'team_seats' => 5,
            'raw_file_limit' => null,
            'features' => ['selection', 'proofing', 'collection', 'raw_files', 'custom_domain', 'remove_branding', 'advanced_analytics', 'team', 'white_label', 'api'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Build Your Own Add-ons
    |--------------------------------------------------------------------------
    */

    'build_your_own' => [
        'base_price_monthly' => 500, // $5
        'base_price_annual' => 5000, // $50 (2 months free)
        'addons' => [
            'proofing' => ['price_monthly' => 500, 'price_annual' => 5000],
            'raw_files' => ['price_monthly' => 800, 'price_annual' => 8000],
            'storage_25gb' => ['price_monthly' => 200, 'price_annual' => 2000, 'storage_bytes' => 25 * 1024 * 1024 * 1024],
            'storage_50gb' => ['price_monthly' => 400, 'price_annual' => 4000, 'storage_bytes' => 50 * 1024 * 1024 * 1024],
            'storage_100gb' => ['price_monthly' => 700, 'price_annual' => 7000, 'storage_bytes' => 100 * 1024 * 1024 * 1024],
            'storage_250gb' => ['price_monthly' => 1500, 'price_annual' => 15000, 'storage_bytes' => 250 * 1024 * 1024 * 1024],
            'storage_500gb' => ['price_monthly' => 2500, 'price_annual' => 25000, 'storage_bytes' => 500 * 1024 * 1024 * 1024],
            'extra_5_projects' => ['price_monthly' => 200, 'price_annual' => 2000],
            'extra_10_revisions' => ['price_monthly' => 300, 'price_annual' => 3000],
            'remove_branding' => ['price_monthly' => 300, 'price_annual' => 3000],
        ],
        'base_storage_bytes' => 5 * 1024 * 1024 * 1024,
        'base_project_limit' => 3,
        'annual_discount_months' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Founder's Pricing
    |--------------------------------------------------------------------------
    */

    'founders' => [
        'enabled' => true,
        'max_customers' => 500,
        'discount_percent' => 40,
        'tier_prices' => [
            'pro' => 900, // $9/month
            'studio' => 2100, // $21/month
            'business' => 3500, // $35/month
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Tier Soft Cap (Unlimited Storage)
    |--------------------------------------------------------------------------
    */

    'business_storage_soft_cap_bytes' => 2 * 1024 * 1024 * 1024 * 1024, // 2TB
];
