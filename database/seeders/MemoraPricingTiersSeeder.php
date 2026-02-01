<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraPricingTier;
use Illuminate\Database\Seeder;

class MemoraPricingTiersSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'Perfect for getting started',
                'price_monthly_cents' => 0,
                'price_annual_cents' => 0,
                'storage_bytes' => 5 * 1024 * 1024 * 1024,
                'project_limit' => 3,
                'collection_limit' => 2,
                'max_revisions' => 0,
                'watermark_limit' => 1,
                'preset_limit' => 1,
                'team_seats' => 1,
                'raw_file_limit' => 0,
                'features' => ['selection', 'collection'],
                'features_display' => [
                    '3 Projects',
                    '2 Collections',
                    '5GB Storage',
                    'Selection + Collection phases',
                    'Mazeloot branding',
                    'Community support',
                ],
                'sort_order' => 1,
                'is_popular' => false,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'For solo photographers with regular clients',
                'price_monthly_cents' => 1500,
                'price_annual_cents' => 14400,
                'storage_bytes' => 100 * 1024 * 1024 * 1024,
                'project_limit' => null,
                'collection_limit' => null,
                'max_revisions' => 5,
                'watermark_limit' => 3,
                'preset_limit' => 5,
                'team_seats' => 1,
                'raw_file_limit' => 0,
                'features' => ['selection', 'proofing', 'collection', 'custom_domain', 'remove_branding'],
                'features_display' => [
                    'Unlimited Projects',
                    'Unlimited Collections',
                    '100GB Storage',
                    'Proofing phase (5 revisions)',
                    'Custom domain',
                    'Remove branding',
                    '3 Watermarks, 5 Presets',
                    'Basic analytics',
                    'Download PIN',
                    'Email support',
                ],
                'sort_order' => 2,
                'is_popular' => true,
            ],
            [
                'slug' => 'studio',
                'name' => 'Studio',
                'description' => 'For growing photographers and event specialists',
                'price_monthly_cents' => 3500,
                'price_annual_cents' => 33600,
                'storage_bytes' => 500 * 1024 * 1024 * 1024,
                'project_limit' => null,
                'collection_limit' => null,
                'max_revisions' => 10,
                'watermark_limit' => null,
                'preset_limit' => null,
                'team_seats' => 1,
                'raw_file_limit' => null,
                'features' => ['selection', 'proofing', 'collection', 'raw_files', 'custom_domain', 'remove_branding', 'advanced_analytics'],
                'features_display' => [
                    'Everything in Pro',
                    '500GB Storage',
                    '10 revisions per proofing',
                    'Raw Files phase',
                    'Unlimited watermarks & presets',
                    'Advanced analytics',
                    'Client email registration',
                    'Auto-expiry, slideshow',
                    'Social sharing',
                    'Priority support',
                ],
                'sort_order' => 3,
                'is_popular' => false,
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'description' => 'For studios and high-volume operations',
                'price_monthly_cents' => 5900,
                'price_annual_cents' => 58800,
                'storage_bytes' => null,
                'project_limit' => null,
                'collection_limit' => null,
                'max_revisions' => 20,
                'watermark_limit' => null,
                'preset_limit' => null,
                'team_seats' => 5,
                'raw_file_limit' => null,
                'features' => ['selection', 'proofing', 'collection', 'raw_files', 'custom_domain', 'remove_branding', 'advanced_analytics', 'team', 'white_label', 'api'],
                'features_display' => [
                    'Everything in Studio',
                    'Unlimited Storage',
                    '20 revisions per proofing',
                    'Team collaboration (5 seats)',
                    'White-label',
                    'Multi-brand support',
                    'API access',
                    'Advanced SEO',
                    '24/7 support',
                ],
                'sort_order' => 4,
                'is_popular' => false,
            ],
        ];

        foreach ($tiers as $tier) {
            MemoraPricingTier::updateOrCreate(
                ['slug' => $tier['slug']],
                $tier
            );
        }
    }
}
