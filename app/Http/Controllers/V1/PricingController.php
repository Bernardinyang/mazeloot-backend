<?php

namespace App\Http\Controllers\V1;

use App\Domains\Memora\Models\MemoraByoAddon;
use App\Domains\Memora\Models\MemoraByoConfig;
use App\Domains\Memora\Models\MemoraPricingTier;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    public function tiers(): JsonResponse
    {
        $tiers = MemoraPricingTier::getAllActive()->map(fn ($t) => [
            'id' => $t->slug,
            'name' => $t->name,
            'description' => $t->description,
            'price_monthly_cents' => $t->price_monthly_cents,
            'price_annual_cents' => $t->price_annual_cents,
            'popular' => $t->is_popular,
            'features' => $t->features_display ?? [],
        ]);

        return ApiResponse::successOk($tiers->values()->all());
    }

    public function buildYourOwn(): JsonResponse
    {
        $config = MemoraByoConfig::getConfig();
        $addons = MemoraByoAddon::active()->ordered()->get();

        if (! $config) {
            return ApiResponse::errorNotFound('Build Your Own pricing not configured');
        }

        $checkboxAddons = $addons->where('type', 'checkbox')->values()->map(fn ($a) => [
            'slug' => $a->slug,
            'label' => $a->label,
            'price_monthly_cents' => $a->price_monthly_cents,
            'price_annual_cents' => $a->price_annual_cents,
            'is_default' => $a->is_default,
        ]);

        $storageAddons = $addons->where('type', 'storage')->values()->map(fn ($a) => [
            'slug' => $a->slug,
            'label' => $a->label,
            'price_monthly_cents' => $a->price_monthly_cents,
            'price_annual_cents' => $a->price_annual_cents,
            'storage_bytes' => $a->storage_bytes,
            'is_default' => $a->is_default,
        ]);

        return ApiResponse::successOk([
            'base' => [
                'price_monthly_cents' => $config->base_price_monthly_cents,
                'price_annual_cents' => $config->base_price_annual_cents,
                'storage_bytes' => (int) $config->base_storage_bytes,
                'project_limit' => $config->base_project_limit,
                'annual_discount_months' => $config->annual_discount_months,
            ],
            'checkbox_addons' => $checkboxAddons,
            'storage_addons' => $storageAddons,
        ]);
    }
}
