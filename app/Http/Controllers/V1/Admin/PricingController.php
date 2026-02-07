<?php

namespace App\Http\Controllers\V1\Admin;

use App\Domains\Memora\Models\MemoraByoAddon;
use App\Domains\Memora\Models\MemoraByoConfig;
use App\Domains\Memora\Models\MemoraPricingTier;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PricingController extends Controller
{
    /**
     * List all pricing tiers (including inactive).
     */
    public function tiers(): JsonResponse
    {
        $tiers = MemoraPricingTier::ordered()->get()->map(fn ($t) => $this->tierToArray($t));

        return ApiResponse::successOk($tiers->all());
    }

    /**
     * Get single tier by slug.
     */
    public function showTier(string $slug): JsonResponse
    {
        $tier = MemoraPricingTier::where('slug', $slug)->first();
        if (! $tier) {
            return ApiResponse::errorNotFound('Tier not found.');
        }

        return ApiResponse::successOk($this->tierToArray($tier));
    }

    /**
     * Create a new pricing tier.
     */
    public function storeTier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string|max:64|unique:memora_pricing_tiers,slug|regex:/^[a-z0-9_-]+$/',
            'name' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::errorValidation('Validation failed', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $name = $data['name'] ?? $data['slug'];
        $sortOrder = (int) MemoraPricingTier::max('sort_order') + 1;

        $tier = MemoraPricingTier::create([
            'slug' => $data['slug'],
            'name' => $name,
            'description' => null,
            'price_monthly_cents' => 0,
            'price_annual_cents' => 0,
            'storage_bytes' => null,
            'project_limit' => null,
            'collection_limit' => null,
            'selection_limit' => null,
            'proofing_limit' => null,
            'set_limit_per_phase' => null,
            'max_revisions' => 0,
            'watermark_limit' => null,
            'preset_limit' => null,
            'team_seats' => 1,
            'raw_file_limit' => null,
            'features' => [],
            'features_display' => [],
            'capabilities' => [],
            'sort_order' => $sortOrder,
            'is_popular' => false,
            'is_active' => true,
        ]);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'created',
                $tier,
                'Admin created pricing tier',
                ['slug' => $tier->slug],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log pricing tier activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::successCreated($this->tierToArray($tier->fresh()));
    }

    /**
     * Update a pricing tier.
     */
    public function updateTier(Request $request, string $slug): JsonResponse
    {
        $tier = MemoraPricingTier::where('slug', $slug)->first();
        if (! $tier) {
            return ApiResponse::errorNotFound('Tier not found.');
        }

        $rules = [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'price_monthly_cents' => 'sometimes|integer|min:0',
            'price_annual_cents' => 'sometimes|integer|min:0',
            'storage_bytes' => 'nullable|integer|min:0',
            'project_limit' => 'nullable|integer|min:0',
            'collection_limit' => 'nullable|integer|min:0',
            'selection_limit' => 'nullable|integer|min:0',
            'proofing_limit' => 'nullable|integer|min:0',
            'set_limit_per_phase' => 'nullable|integer|min:0',
            'max_revisions' => 'sometimes|integer|min:0',
            'watermark_limit' => 'nullable|integer|min:0',
            'preset_limit' => 'nullable|integer|min:0',
            'team_seats' => 'sometimes|integer|min:1',
            'raw_file_limit' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'string|max:64',
            'features_display' => 'nullable|array',
            'features_display.*' => 'string|max:500',
            'capabilities' => 'nullable|array',
            'sort_order' => 'sometimes|integer|min:0',
            'is_popular' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ApiResponse::errorValidation('Validation failed', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        if (array_key_exists('features', $data) && $data['features'] !== null) {
            $data['features'] = array_values($data['features']);
        }
        if (array_key_exists('features_display', $data) && $data['features_display'] !== null) {
            $data['features_display'] = array_values($data['features_display']);
        }

        $tier->update($data);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $tier,
                'Admin updated pricing tier',
                ['slug' => $tier->slug],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log pricing tier activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::successOk($this->tierToArray($tier->fresh()));
    }

    /**
     * Delete a pricing tier.
     */
    public function destroyTier(Request $request, string $slug): JsonResponse
    {
        $tier = MemoraPricingTier::where('slug', $slug)->first();
        if (! $tier) {
            return ApiResponse::errorNotFound('Tier not found.');
        }
        $slugVal = $tier->slug;
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'deleted',
                $tier,
                'Admin deleted pricing tier',
                ['slug' => $slugVal],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log pricing tier activity', ['error' => $e->getMessage()]);
        }
        $tier->delete();

        return ApiResponse::success(null, 204);
    }

    /**
     * Get Build Your Own config.
     */
    public function byoConfig(): JsonResponse
    {
        $config = MemoraByoConfig::first();
        if (! $config) {
            return ApiResponse::errorNotFound('BYO config not found.');
        }

        $basePriceMonthly = (int) $config->base_price_monthly_cents;
        $basePriceAnnual = (int) $config->base_price_annual_cents;
        $baseCostMonthly = $config->base_cost_monthly_cents !== null ? (int) $config->base_cost_monthly_cents : null;
        $baseCostAnnual = $config->base_cost_annual_cents !== null ? (int) $config->base_cost_annual_cents : null;

        return ApiResponse::successOk([
            'base_price_monthly_cents' => $basePriceMonthly,
            'base_price_annual_cents' => $basePriceAnnual,
            'base_cost_monthly_cents' => $baseCostMonthly,
            'base_cost_annual_cents' => $baseCostAnnual,
            'base_storage_bytes' => (int) $config->base_storage_bytes,
            'base_project_limit' => $config->base_project_limit,
            'base_selection_limit' => (int) ($config->base_selection_limit ?? 0),
            'base_proofing_limit' => (int) ($config->base_proofing_limit ?? 0),
            'base_collection_limit' => (int) ($config->base_collection_limit ?? 0),
            'base_raw_file_limit' => (int) ($config->base_raw_file_limit ?? 0),
            'base_max_revisions' => (int) ($config->base_max_revisions ?? 0),
            'annual_discount_months' => $config->annual_discount_months,
            'base_profit_monthly_cents' => $baseCostMonthly !== null ? $basePriceMonthly - $baseCostMonthly : null,
            'base_profit_annual_cents' => $baseCostAnnual !== null ? $basePriceAnnual - $baseCostAnnual : null,
            'base_margin_monthly_pct' => $baseCostMonthly !== null && $basePriceMonthly > 0
                ? round((($basePriceMonthly - $baseCostMonthly) / $basePriceMonthly) * 100, 2)
                : null,
            'base_margin_annual_pct' => $baseCostAnnual !== null && $basePriceAnnual > 0
                ? round((($basePriceAnnual - $baseCostAnnual) / $basePriceAnnual) * 100, 2)
                : null,
        ]);
    }

    /**
     * Update Build Your Own config.
     */
    public function updateByoConfig(Request $request): JsonResponse
    {
        $config = MemoraByoConfig::first();
        if (! $config) {
            $config = MemoraByoConfig::create([
                'base_price_monthly_cents' => 500,
                'base_price_annual_cents' => 5000,
                'base_storage_bytes' => 5 * 1024 * 1024 * 1024,
                'base_project_limit' => 1,
                'base_selection_limit' => 1,
                'base_proofing_limit' => 1,
                'base_collection_limit' => 1,
                'base_raw_file_limit' => 1,
                'base_max_revisions' => 0,
                'annual_discount_months' => 2,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'base_price_monthly_cents' => 'sometimes|integer|min:0',
            'base_price_annual_cents' => 'sometimes|integer|min:0',
            'base_cost_monthly_cents' => 'nullable|integer|min:0',
            'base_cost_annual_cents' => 'nullable|integer|min:0',
            'base_storage_bytes' => 'sometimes|integer|min:0',
            'base_project_limit' => 'sometimes|integer|min:0',
            'base_selection_limit' => 'sometimes|integer|min:0',
            'base_proofing_limit' => 'sometimes|integer|min:0',
            'base_collection_limit' => 'sometimes|integer|min:0',
            'base_raw_file_limit' => 'sometimes|integer|min:0',
            'base_max_revisions' => 'sometimes|integer|min:0',
            'annual_discount_months' => 'sometimes|integer|min:0|max:12',
        ]);
        if ($validator->fails()) {
            return ApiResponse::errorValidation('Validation failed', $validator->errors()->toArray());
        }

        $config->update($validator->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $config,
                'Admin updated BYO config',
                null,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log BYO config activity', ['error' => $e->getMessage()]);
        }

        $basePriceMonthly = (int) $config->base_price_monthly_cents;
        $basePriceAnnual = (int) $config->base_price_annual_cents;
        $baseCostMonthly = $config->base_cost_monthly_cents !== null ? (int) $config->base_cost_monthly_cents : null;
        $baseCostAnnual = $config->base_cost_annual_cents !== null ? (int) $config->base_cost_annual_cents : null;

        return ApiResponse::successOk([
            'base_price_monthly_cents' => $basePriceMonthly,
            'base_price_annual_cents' => $basePriceAnnual,
            'base_cost_monthly_cents' => $baseCostMonthly,
            'base_cost_annual_cents' => $baseCostAnnual,
            'base_storage_bytes' => (int) $config->base_storage_bytes,
            'base_project_limit' => $config->base_project_limit,
            'base_selection_limit' => (int) ($config->base_selection_limit ?? 0),
            'base_proofing_limit' => (int) ($config->base_proofing_limit ?? 0),
            'base_collection_limit' => (int) ($config->base_collection_limit ?? 0),
            'base_raw_file_limit' => (int) ($config->base_raw_file_limit ?? 0),
            'base_max_revisions' => (int) ($config->base_max_revisions ?? 0),
            'annual_discount_months' => $config->annual_discount_months,
            'base_profit_monthly_cents' => $baseCostMonthly !== null ? $basePriceMonthly - $baseCostMonthly : null,
            'base_profit_annual_cents' => $baseCostAnnual !== null ? $basePriceAnnual - $baseCostAnnual : null,
            'base_margin_monthly_pct' => $baseCostMonthly !== null && $basePriceMonthly > 0
                ? round((($basePriceMonthly - $baseCostMonthly) / $basePriceMonthly) * 100, 2)
                : null,
            'base_margin_annual_pct' => $baseCostAnnual !== null && $basePriceAnnual > 0
                ? round((($basePriceAnnual - $baseCostAnnual) / $basePriceAnnual) * 100, 2)
                : null,
        ]);
    }

    /**
     * List allowed BYO checkbox addon slugs (for admin create dropdown).
     * Storage addons: any slug allowed; admin sets size and it is used for limits.
     */
    public function byoAddonSlugs(): JsonResponse
    {
        $slugs = config('pricing.byo_addon_checkbox_slugs', []);
        $list = array_map(fn ($slug) => ['slug' => $slug, 'type' => 'checkbox'], $slugs);

        return ApiResponse::successOk($list);
    }

    /**
     * List all BYO addons (including inactive).
     */
    public function byoAddons(): JsonResponse
    {
        $addons = MemoraByoAddon::ordered()->get()->map(fn ($a) => $this->addonToArray($a));

        return ApiResponse::successOk($addons->all());
    }

    /**
     * Create BYO addon.
     */
    public function storeByoAddon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string|max:64|unique:memora_byo_addons,slug',
            'label' => 'required|string|max:255',
            'type' => 'required|in:checkbox,storage',
            'price_monthly_cents' => 'required|integer|min:0',
            'price_annual_cents' => 'required|integer|min:0',
            'cost_monthly_cents' => 'nullable|integer|min:0',
            'cost_annual_cents' => 'nullable|integer|min:0',
            'storage_bytes' => 'nullable|integer|min:0',
            'selection_limit_granted' => 'nullable|integer|min:0',
            'proofing_limit_granted' => 'nullable|integer|min:0',
            'collection_limit_granted' => 'nullable|integer|min:0',
            'project_limit_granted' => 'nullable|integer|min:0',
            'raw_file_limit_granted' => 'nullable|integer|min:0',
            'max_revisions_granted' => 'nullable|integer|min:0',
            'sort_order' => 'sometimes|integer|min:0',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return ApiResponse::errorValidation('Validation failed', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $allowlistError = $this->validateByoAddonSlugAndType($data['slug'], $data['type']);
        if ($allowlistError) {
            return ApiResponse::errorValidation('Validation failed', ['slug' => [$allowlistError]]);
        }

        $addon = MemoraByoAddon::create($data);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'created',
                $addon,
                'Admin created BYO addon',
                ['slug' => $addon->slug],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log BYO addon activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::successCreated($this->addonToArray($addon));
    }

    /**
     * Update BYO addon.
     */
    public function updateByoAddon(Request $request, int $id): JsonResponse
    {
        $addon = MemoraByoAddon::find($id);
        if (! $addon) {
            return ApiResponse::errorNotFound('Addon not found.');
        }

        $validator = Validator::make($request->all(), [
            'slug' => 'sometimes|string|max:64|unique:memora_byo_addons,slug,'.$id,
            'label' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:checkbox,storage',
            'price_monthly_cents' => 'sometimes|integer|min:0',
            'price_annual_cents' => 'sometimes|integer|min:0',
            'cost_monthly_cents' => 'nullable|integer|min:0',
            'cost_annual_cents' => 'nullable|integer|min:0',
            'storage_bytes' => 'nullable|integer|min:0',
            'selection_limit_granted' => 'nullable|integer|min:0',
            'proofing_limit_granted' => 'nullable|integer|min:0',
            'collection_limit_granted' => 'nullable|integer|min:0',
            'project_limit_granted' => 'nullable|integer|min:0',
            'raw_file_limit_granted' => 'nullable|integer|min:0',
            'max_revisions_granted' => 'nullable|integer|min:0',
            'sort_order' => 'sometimes|integer|min:0',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return ApiResponse::errorValidation('Validation failed', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $slug = $data['slug'] ?? $addon->slug;
        $type = $data['type'] ?? $addon->type;
        $allowlistError = $this->validateByoAddonSlugAndType($slug, $type);
        if ($allowlistError) {
            return ApiResponse::errorValidation('Validation failed', ['slug' => [$allowlistError]]);
        }

        $addon->update($data);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $addon,
                'Admin updated BYO addon',
                ['slug' => $addon->slug],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log BYO addon activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::successOk($this->addonToArray($addon->fresh()));
    }

    /**
     * Delete BYO addon.
     */
    public function destroyByoAddon(Request $request, int $id): JsonResponse
    {
        $addon = MemoraByoAddon::find($id);
        if (! $addon) {
            return ApiResponse::errorNotFound('Addon not found.');
        }
        $slugVal = $addon->slug;
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'deleted',
                $addon,
                'Admin deleted BYO addon',
                ['slug' => $slugVal],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log BYO addon activity', ['error' => $e->getMessage()]);
        }
        $addon->delete();

        return ApiResponse::success(null, 204);
    }

    protected function tierToArray(MemoraPricingTier $t): array
    {
        return [
            'id' => $t->id,
            'slug' => $t->slug,
            'name' => $t->name,
            'description' => $t->description,
            'price_monthly_cents' => $t->price_monthly_cents,
            'price_annual_cents' => $t->price_annual_cents,
            'storage_bytes' => $t->storage_bytes !== null ? (int) $t->storage_bytes : null,
            'project_limit' => $t->project_limit !== null ? (int) $t->project_limit : null,
            'collection_limit' => $t->collection_limit !== null ? (int) $t->collection_limit : null,
            'selection_limit' => $t->selection_limit !== null ? (int) $t->selection_limit : null,
            'proofing_limit' => $t->proofing_limit !== null ? (int) $t->proofing_limit : null,
            'set_limit_per_phase' => $t->set_limit_per_phase !== null ? (int) $t->set_limit_per_phase : null,
            'max_revisions' => (int) $t->max_revisions,
            'watermark_limit' => $t->watermark_limit !== null ? (int) $t->watermark_limit : null,
            'preset_limit' => $t->preset_limit !== null ? (int) $t->preset_limit : null,
            'team_seats' => (int) $t->team_seats,
            'raw_file_limit' => $t->raw_file_limit !== null ? (int) $t->raw_file_limit : null,
            'features' => $t->features ?? [],
            'features_display' => $t->features_display ?? [],
            'capabilities' => $t->capabilities ?? [],
            'sort_order' => (int) $t->sort_order,
            'is_popular' => (bool) $t->is_popular,
            'is_active' => (bool) $t->is_active,
        ];
    }

    /**
     * Validate slug/type for BYO addon. Checkbox: slug must be in allowlist. Storage: any slug allowed.
     */
    protected function validateByoAddonSlugAndType(string $slug, string $type): ?string
    {
        if ($type === 'storage') {
            return null;
        }
        $allowed = config('pricing.byo_addon_checkbox_slugs', []);
        if (! in_array($slug, $allowed, true)) {
            return 'Checkbox addon slug must be one of: '.implode(', ', $allowed);
        }

        return null;
    }

    protected function addonToArray(MemoraByoAddon $a): array
    {
        $priceMonthly = (int) $a->price_monthly_cents;
        $priceAnnual = (int) $a->price_annual_cents;
        $costMonthly = $a->cost_monthly_cents !== null ? (int) $a->cost_monthly_cents : null;
        $costAnnual = $a->cost_annual_cents !== null ? (int) $a->cost_annual_cents : null;

        return [
            'id' => $a->id,
            'slug' => $a->slug,
            'label' => $a->label,
            'type' => $a->type,
            'price_monthly_cents' => $priceMonthly,
            'price_annual_cents' => $priceAnnual,
            'cost_monthly_cents' => $costMonthly,
            'cost_annual_cents' => $costAnnual,
            'storage_bytes' => $a->storage_bytes !== null ? (int) $a->storage_bytes : null,
            'selection_limit_granted' => $a->selection_limit_granted !== null ? (int) $a->selection_limit_granted : null,
            'proofing_limit_granted' => $a->proofing_limit_granted !== null ? (int) $a->proofing_limit_granted : null,
            'collection_limit_granted' => $a->collection_limit_granted !== null ? (int) $a->collection_limit_granted : null,
            'project_limit_granted' => $a->project_limit_granted !== null ? (int) $a->project_limit_granted : null,
            'raw_file_limit_granted' => $a->raw_file_limit_granted !== null ? (int) $a->raw_file_limit_granted : null,
            'max_revisions_granted' => $a->max_revisions_granted !== null ? (int) $a->max_revisions_granted : null,
            'sort_order' => (int) $a->sort_order,
            'is_default' => (bool) $a->is_default,
            'is_active' => (bool) $a->is_active,
            'profit_monthly_cents' => $costMonthly !== null ? $priceMonthly - $costMonthly : null,
            'profit_annual_cents' => $costAnnual !== null ? $priceAnnual - $costAnnual : null,
            'margin_monthly_pct' => $costMonthly !== null && $priceMonthly > 0
                ? round((($priceMonthly - $costMonthly) / $priceMonthly) * 100, 2)
                : null,
            'margin_annual_pct' => $costAnnual !== null && $priceAnnual > 0
                ? round((($priceAnnual - $costAnnual) / $priceAnnual) * 100, 2)
                : null,
        ];
    }
}
