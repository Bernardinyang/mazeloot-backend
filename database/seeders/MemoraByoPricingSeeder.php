<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraByoAddon;
use App\Domains\Memora\Models\MemoraByoConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemoraByoPricingSeeder extends Seeder
{
    public function run(): void
    {
        $configAttrs = [
            'base_price_monthly_cents' => 500,
            'base_price_annual_cents' => 5000,
            'base_cost_monthly_cents' => 200,
            'base_cost_annual_cents' => 2000,
            'base_storage_bytes' => 5 * 1024 * 1024 * 1024,
            'base_project_limit' => 3,
            'base_selection_limit' => 1,
            'base_proofing_limit' => 1,
            'base_collection_limit' => 1,
            'base_raw_file_limit' => 1,
            'base_max_revisions' => 3,
            'annual_discount_months' => 2,
        ];
        $config = MemoraByoConfig::first();
        if ($config) {
            $config->update($configAttrs);
        } else {
            MemoraByoConfig::create($configAttrs);
        }

        MemoraByoAddon::whereIn('slug', ['extra_10_revisions', 'extra_5_projects'])->delete();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE memora_byo_addons MODIFY storage_bytes BIGINT UNSIGNED NULL');
        }

        // Order: workflow first (Projects → Selections → Proofing → Collections → Raw files), then branding, then storage
        // [slug, label, type, monthly, annual, bytes, order, default, selGranted, proofGranted, collGranted, projGranted, rawFileGranted, maxRevGranted, costMonthly, costAnnual]
        $addons = [
            ['projects', 'Projects (per unit)', 'checkbox', 150, 1500, null, 1, false, null, null, null, 1, null, null, 60, 600],
            ['selections', 'Selection phases (per unit)', 'checkbox', 200, 2000, null, 2, false, 1, null, null, null, null, null, 80, 800],
            ['proofing', 'Proofing phase (5 revisions per unit)', 'checkbox', 500, 5000, null, 3, true, null, 1, null, null, null, 5, 200, 2000],
            ['collections', 'Collection phases (per unit)', 'checkbox', 200, 2000, null, 4, false, null, null, 1, null, null, null, 80, 800],
            ['raw_files', 'Raw Files phase (per unit)', 'checkbox', 800, 8000, null, 5, false, null, null, null, null, 1, null, 320, 3200],
            ['remove_branding', 'Remove Mazeloot branding', 'checkbox', 300, 3000, null, 6, false, null, null, null, null, null, null, 100, 1000],
            ['storage_25gb', '+25 GB (30 GB total)', 'storage', 200, 2000, 25 * 1024 * 1024 * 1024, 7, true, null, null, null, null, null, null, 80, 800],
            ['storage_50gb', '+50 GB (55 GB total)', 'storage', 400, 4000, 50 * 1024 * 1024 * 1024, 8, false, null, null, null, null, null, null, 160, 1600],
            ['storage_100gb', '+100 GB (105 GB total)', 'storage', 700, 7000, 100 * 1024 * 1024 * 1024, 9, false, null, null, null, null, null, null, 280, 2800],
            ['storage_250gb', '+250 GB', 'storage', 1500, 15000, 250 * 1024 * 1024 * 1024, 10, false, null, null, null, null, null, null, 600, 6000],
            ['storage_500gb', '+500 GB', 'storage', 2500, 25000, 500 * 1024 * 1024 * 1024, 11, false, null, null, null, null, null, null, 1000, 10000],
            ['branding_editable', 'Editable branding', 'checkbox', 0, 0, null, 12, false, null, null, null, null, null, null, 0, 0],
            ['collection_display_enabled', 'Collection display', 'checkbox', 0, 0, null, 13, false, null, null, null, null, null, null, 0, 0],
            ['homepage_enabled', 'Custom homepage', 'checkbox', 0, 0, null, 14, false, null, null, null, null, null, null, 0, 0],
            ['legal_documents_enabled', 'Legal documents', 'checkbox', 0, 0, null, 15, false, null, null, null, null, null, null, 0, 0],
            ['photo_quality_enabled', 'Photo quality settings', 'checkbox', 0, 0, null, 16, false, null, null, null, null, null, null, 0, 0],
            ['social_links_enabled', 'Social links', 'checkbox', 0, 0, null, 17, false, null, null, null, null, null, null, 0, 0],
            ['support_24_7', '24/7 support', 'checkbox', 500, 5000, null, 18, false, null, null, null, null, null, null, 200, 2000],
        ];

        foreach ($addons as $i => [$slug, $label, $type, $monthly, $annual, $bytes, $order, $default, $selGranted, $proofGranted, $collGranted, $projGranted, $rawFileGranted, $maxRevGranted, $costMonthly, $costAnnual]) {
            MemoraByoAddon::updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => $label,
                    'type' => $type,
                    'price_monthly_cents' => $monthly,
                    'price_annual_cents' => $annual,
                    'cost_monthly_cents' => $costMonthly,
                    'cost_annual_cents' => $costAnnual,
                    'storage_bytes' => $bytes,
                    'selection_limit_granted' => $selGranted,
                    'proofing_limit_granted' => $proofGranted,
                    'collection_limit_granted' => $collGranted,
                    'project_limit_granted' => $projGranted,
                    'raw_file_limit_granted' => $rawFileGranted,
                    'max_revisions_granted' => $maxRevGranted,
                    'sort_order' => $order,
                    'is_default' => $default,
                    'is_active' => true,
                ]
            );
        }
    }
}
