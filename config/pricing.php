<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAT (Value Added Tax)
    |--------------------------------------------------------------------------
    | Rate as decimal (e.g. 0.20 = 20%). Applied to subscription subtotal; user pays subtotal + VAT.
    | Set to 0 to disable. Override with PRICING_VAT_RATE env.
    */
    'vat_rate' => (float) (env('PRICING_VAT_RATE', 0)),

    /*
    |--------------------------------------------------------------------------
    | Memora Tier Pricing
    |--------------------------------------------------------------------------
    |
    | Fixed tier structure for Memora. Prices in cents (USD).
    | Storage limits in bytes.
    |
    | Starter: All phases, 1 set/phase, Mazeloot branding, Community support,
    | no homepage/edit branding/social/collection display/photo quality/legal.
    | Pro: Unlimited projects/collections/sets, remove branding, homepage,
    | edit branding, social links, legal, photo quality, Email support.
    | Studio: + Raw Files, advanced analytics, Priority support.
    | Business: + Team, white label, API, 24/7 support.
    |
    | Tier limits: selection_limit = max selection phases user can create.
    | Per-set "selection limit" (max files client can select) is on MemoraSelection/MemoraMediaSet.
    |
    */

    'tiers' => [
        'starter' => [
            'name' => 'Starter',
            'price_monthly' => 0,
            'price_annual' => 0,
            'storage_bytes' => 5 * 1024 * 1024 * 1024, // 5GB
            'project_limit' => 1,
            'collection_limit' => 1,
            'selection_limit' => 1, // max selection phases (per-set "selection limit" = max files client can select)
            'proofing_limit' => 1,
            'max_revisions' => 0,
            'watermark_limit' => 1,
            'preset_limit' => 0,
            'set_limit_per_phase' => 1,
            'team_seats' => 1,
            'raw_file_limit' => 1,
            'features' => ['selection', 'proofing', 'collection', 'raw_files'],
            'capabilities' => [
                'homepage_enabled' => false,
                'branding_editable' => false,
                'social_links_enabled' => false,
                'collection_display_enabled' => false,
                'photo_quality_enabled' => false,
                'legal_documents_enabled' => false,
                'support_24_7' => false,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'price_monthly' => 1500, // $15
            'price_annual' => 14400, // $144 ($12 x 12)
            'storage_bytes' => 100 * 1024 * 1024 * 1024, // 100GB
            'project_limit' => null,
            'collection_limit' => null,
            'selection_limit' => null,
            'proofing_limit' => null,
            'max_revisions' => 5,
            'watermark_limit' => 3,
            'preset_limit' => 5,
            'set_limit_per_phase' => null,
            'team_seats' => 1,
            'raw_file_limit' => 0,
            'features' => ['selection', 'proofing', 'collection', 'custom_domain', 'remove_branding'],
            'capabilities' => [
                'homepage_enabled' => true,
                'branding_editable' => true,
                'social_links_enabled' => true,
                'collection_display_enabled' => true,
                'photo_quality_enabled' => true,
                'legal_documents_enabled' => true,
                'support_24_7' => false,
            ],
        ],
        'studio' => [
            'name' => 'Studio',
            'price_monthly' => 3500, // $35
            'price_annual' => 33600, // $336 ($28 x 12)
            'storage_bytes' => 500 * 1024 * 1024 * 1024, // 500GB
            'project_limit' => null,
            'collection_limit' => null,
            'selection_limit' => null,
            'proofing_limit' => null,
            'max_revisions' => 10,
            'watermark_limit' => null,
            'preset_limit' => null,
            'set_limit_per_phase' => null,
            'team_seats' => 1,
            'raw_file_limit' => null,
            'features' => ['selection', 'proofing', 'collection', 'raw_files', 'custom_domain', 'remove_branding', 'advanced_analytics'],
            'capabilities' => [
                'homepage_enabled' => true,
                'branding_editable' => true,
                'social_links_enabled' => true,
                'collection_display_enabled' => true,
                'photo_quality_enabled' => true,
                'legal_documents_enabled' => true,
                'support_24_7' => false,
            ],
        ],
        'business' => [
            'name' => 'Business',
            'price_monthly' => 5900, // $59
            'price_annual' => 58800, // $588 ($49 x 12)
            'storage_bytes' => null, // unlimited (use soft cap 2TB in enforcement)
            'project_limit' => null,
            'collection_limit' => null,
            'selection_limit' => null,
            'proofing_limit' => null,
            'max_revisions' => 20,
            'watermark_limit' => null,
            'preset_limit' => null,
            'set_limit_per_phase' => null,
            'team_seats' => 5,
            'raw_file_limit' => null,
            'features' => ['selection', 'proofing', 'collection', 'raw_files', 'custom_domain', 'remove_branding', 'advanced_analytics', 'team', 'white_label', 'api'],
            'capabilities' => [
                'homepage_enabled' => true,
                'branding_editable' => true,
                'social_links_enabled' => true,
                'collection_display_enabled' => true,
                'photo_quality_enabled' => true,
                'legal_documents_enabled' => true,
                'support_24_7' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | BYO Checkbox Addon Slug Allowlist
    |--------------------------------------------------------------------------
    | Only these slugs can be used for checkbox addons (they unlock product
    | features in TierService::getFeaturesForByo). Storage addons: any slug
    | and any storage_bytes; admin defines size and it is used for limits.
    | Aligned with fixed tiers: Pro has remove_branding/custom_domain;
    | Studio + advanced_analytics; Business + team/white_label/api.
    |
    */
    'byo_addon_checkbox_slugs' => [
        'proofing',
        'raw_files',
        'remove_branding',
        'custom_domain',
        'advanced_analytics',
        'team',
        'white_label',
        'api',
        'selections',
        'collections',
        'projects',
        'branding_editable',
        'collection_display_enabled',
        'homepage_enabled',
        'legal_documents_enabled',
        'photo_quality_enabled',
        'social_links_enabled',
        'support_24_7',
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
