<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraByoAddon;
use App\Domains\Memora\Models\MemoraByoConfig;
use Illuminate\Database\Seeder;

class MemoraByoPricingSeeder extends Seeder
{
    public function run(): void
    {
        MemoraByoConfig::firstOrCreate(
            [],
            [
                'base_price_monthly_cents' => 500,
                'base_price_annual_cents' => 5000,
                'base_storage_bytes' => 5 * 1024 * 1024 * 1024,
                'base_project_limit' => 3,
                'annual_discount_months' => 2,
            ]
        );

        $addons = [
            ['proofing', 'Proofing phase (5 revisions)', 'checkbox', 500, 5000, null, 1, true],
            ['raw_files', 'Raw Files phase', 'checkbox', 800, 8000, null, 2, false],
            ['remove_branding', 'Remove Mazeloot branding', 'checkbox', 300, 3000, null, 3, false],
            ['extra_5_projects', 'Extra 5 Projects', 'checkbox', 200, 2000, null, 4, false],
            ['extra_10_revisions', 'Extra 10 revisions (Proofing)', 'checkbox', 300, 3000, null, 5, false],
            ['storage_25gb', '+25 GB (30 GB total)', 'storage', 200, 2000, 25 * 1024 * 1024 * 1024, 10, true],
            ['storage_50gb', '+50 GB (55 GB total)', 'storage', 400, 4000, 50 * 1024 * 1024 * 1024, 11, false],
            ['storage_100gb', '+100 GB (105 GB total)', 'storage', 700, 7000, 100 * 1024 * 1024 * 1024, 12, false],
            ['storage_250gb', '+250 GB', 'storage', 1500, 15000, 250 * 1024 * 1024 * 1024, 13, false],
            ['storage_500gb', '+500 GB', 'storage', 2500, 25000, 500 * 1024 * 1024 * 1024, 14, false],
        ];

        foreach ($addons as $i => [$slug, $label, $type, $monthly, $annual, $bytes, $order, $default]) {
            MemoraByoAddon::updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => $label,
                    'type' => $type,
                    'price_monthly_cents' => $monthly,
                    'price_annual_cents' => $annual,
                    'storage_bytes' => $bytes,
                    'sort_order' => $order,
                    'is_default' => $default,
                    'is_active' => true,
                ]
            );
        }
    }
}
