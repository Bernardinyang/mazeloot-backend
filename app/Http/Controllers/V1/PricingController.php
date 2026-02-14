<?php

namespace App\Http\Controllers\V1;

use App\Domains\Memora\Models\MemoraByoAddon;
use App\Domains\Memora\Models\MemoraByoConfig;
use App\Domains\Memora\Models\MemoraPricingTier;
use App\Http\Controllers\Controller;
use App\Services\Currency\CurrencyService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PricingController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService
    ) {}

    /**
     * Exchange rates from USD: target smallest unit per 1 USD cent.
     * Frontend uses: converted = round(usdCents * rates[code]); formatMoney(converted, code) or for JPY formatMoney(converted, 'jpy', { inCents: false }).
     */
    public function currencyRates(): JsonResponse
    {
        $currencies = array_keys(config('currency.currencies', []));
        $rates = [];
        foreach ($currencies as $code) {
            if (strtoupper($code) === 'USD') {
                continue;
            }
            $oneUsdInTarget = $this->currencyService->convert(100, 'USD', $code);
            $rates[strtolower($code)] = $oneUsdInTarget / 100;
        }

        return ApiResponse::successOk($rates);
    }

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
        $addons = Cache::remember('api.pricing.byo_addons', 300, fn () => MemoraByoAddon::active()->ordered()->get());

        if (! $config) {
            return ApiResponse::errorNotFound('Build Your Own pricing not configured');
        }

        $checkboxAddons = $addons->where('type', 'checkbox')->values()->map(fn ($a) => [
            'slug' => $a->slug,
            'label' => $a->label,
            'price_monthly_cents' => $a->price_monthly_cents,
            'price_annual_cents' => $a->price_annual_cents,
            'is_default' => $a->is_default,
            'selection_limit_granted' => $a->selection_limit_granted !== null ? (int) $a->selection_limit_granted : null,
            'proofing_limit_granted' => $a->proofing_limit_granted !== null ? (int) $a->proofing_limit_granted : null,
            'collection_limit_granted' => $a->collection_limit_granted !== null ? (int) $a->collection_limit_granted : null,
            'project_limit_granted' => $a->project_limit_granted !== null ? (int) $a->project_limit_granted : null,
            'raw_file_limit_granted' => $a->raw_file_limit_granted !== null ? (int) $a->raw_file_limit_granted : null,
            'max_revisions_granted' => $a->max_revisions_granted !== null ? (int) $a->max_revisions_granted : null,
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
                'base_selection_limit' => (int) ($config->base_selection_limit ?? 0),
                'base_proofing_limit' => (int) ($config->base_proofing_limit ?? 0),
                'base_collection_limit' => (int) ($config->base_collection_limit ?? 0),
                'base_raw_file_limit' => (int) ($config->base_raw_file_limit ?? 0),
                'base_max_revisions' => (int) ($config->base_max_revisions ?? 0),
                'annual_discount_months' => $config->annual_discount_months,
            ],
            'checkbox_addons' => $checkboxAddons,
            'storage_addons' => $storageAddons,
        ]);
    }
}
