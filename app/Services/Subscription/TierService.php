<?php

namespace App\Services\Subscription;

use App\Domains\Memora\Models\MemoraByoAddon;
use App\Domains\Memora\Models\MemoraByoConfig;
use App\Domains\Memora\Models\MemoraPricingTier;
use App\Domains\Memora\Models\MemoraSubscription;
use App\Models\User;

class TierService
{
    protected static array $recommendedTierForFeature = [
        'proofing' => 'pro',
        'raw_files' => 'studio',
    ];

    public function getTier(?User $user = null): string
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return 'starter';
        }

        $active = MemoraSubscription::where('user_uuid', $user->uuid)
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();
        $tier = $active?->tier ?? $user->memora_tier ?? 'starter';
        $validTiers = ['starter', 'pro', 'studio', 'business', 'byo'];

        return in_array($tier, $validTiers, true) ? $tier : 'starter';
    }

    public function getFeatures(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return [];
        }

        $tier = $this->getTier($user);

        if ($tier === 'byo') {
            return $this->getFeaturesForByo($user);
        }

        $config = $this->getConfig($tier, $user);
        $features = $config['features'] ?? [];

        return is_array($features) ? $features : [];
    }

    public function getRecommendedTierForFeature(string $feature): ?string
    {
        return self::$recommendedTierForFeature[$feature] ?? null;
    }

    /**
     * Resolve BYO addons for user (from subscription metadata, or Stripe fallback for Stripe subscriptions with empty metadata).
     *
     * @return array<string, int> slug => quantity
     */
    protected function getResolvedByoAddons(User $user): array
    {
        $subscription = MemoraSubscription::where('user_uuid', $user->uuid)
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();

        if (! $subscription || $subscription->tier !== 'byo') {
            return [];
        }

        $raw = $subscription->metadata['byo_addons'] ?? null;
        $byoAddons = $raw === null ? [] : (is_string($raw) ? (json_decode($raw, true) ?? []) : (is_array($raw) ? $raw : []));
        if ($byoAddons !== []) {
            return $byoAddons;
        }

        if (($subscription->payment_provider ?? '') === 'stripe' && ! empty($subscription->stripe_subscription_id)) {
            try {
                $stripeSub = app(\App\Services\Payment\Providers\StripeProvider::class)->getSubscription($subscription->stripe_subscription_id);
                $meta = $stripeSub->metadata ?? null;
                $subMetaArr = $meta && is_object($meta) && method_exists($meta, 'toArray') ? $meta->toArray() : (is_array($meta) ? $meta : []);
                $fromStripe = $subMetaArr['byo_addons'] ?? null;
                $byoAddons = $fromStripe === null ? [] : (is_string($fromStripe) ? (json_decode($fromStripe, true) ?? []) : (is_array($fromStripe) ? $fromStripe : []));
                if ($byoAddons !== []) {
                    $subscription->update(['metadata' => array_merge($subscription->metadata ?? [], ['byo_addons' => $byoAddons])]);

                    return $byoAddons;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::debug('TierService: could not fetch Stripe subscription metadata for BYO addons', [
                    'subscription_id' => $subscription->stripe_subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    protected function getFeaturesForByo(User $user): array
    {
        $features = ['selection', 'collection', 'project', 'raw_files', 'proofing'];

        $byoAddons = $this->getResolvedByoAddons($user);

        $extraFeatureSlugs = [
            'remove_branding', 'custom_domain', 'advanced_analytics', 'team', 'white_label', 'api',
        ];
        foreach ($extraFeatureSlugs as $slug) {
            if (! empty($byoAddons[$slug])) {
                $features[] = $slug;
            }
        }

        return $features;
    }

    protected static array $byoCapabilitySlugs = [
        'branding_editable', 'collection_display_enabled', 'homepage_enabled',
        'legal_documents_enabled', 'photo_quality_enabled', 'social_links_enabled', 'support_24_7',
    ];

    protected function getCapabilitiesForByo(array $byoAddons): array
    {
        $capabilities = [];
        foreach (self::$byoCapabilitySlugs as $slug) {
            $capabilities[$slug] = ! empty($byoAddons[$slug]);
        }

        return $capabilities;
    }

    /**
     * Resolve BYO plan features and capabilities from addons array (for display in history/status).
     *
     * @param  array<string, int>  $byoAddons  slug => quantity
     * @return array{features: string[], capabilities: array<string, bool>}
     */
    public function resolveByoPlanFromAddons(array $byoAddons): array
    {
        $features = ['selection', 'collection', 'project', 'raw_files', 'proofing'];
        $extraSlugs = ['remove_branding', 'custom_domain', 'advanced_analytics', 'team', 'white_label', 'api'];
        foreach ($extraSlugs as $slug) {
            if (! empty($byoAddons[$slug])) {
                $features[] = $slug;
            }
        }

        return [
            'features' => $features,
            'capabilities' => $this->getCapabilitiesForByo($byoAddons),
        ];
    }

    /**
     * Build addon display list (slug, label, qty, type) from byo_addons array.
     * Excludes capability slugs so they only appear under Capabilities, not Add-ons.
     *
     * @param  array<string, int>  $byoAddons  slug => quantity
     * @return array<int, array{slug: string, label: string, qty: int, type: string|null}>
     */
    public function getByoAddonsDisplay(array $byoAddons): array
    {
        if (empty($byoAddons)) {
            return [];
        }
        $capabilitySlugs = array_fill_keys(self::$byoCapabilitySlugs, true);
        $addons = MemoraByoAddon::whereIn('slug', array_keys($byoAddons))->get()->keyBy('slug');
        $list = [];
        foreach ($byoAddons as $slug => $qty) {
            if (isset($capabilitySlugs[$slug])) {
                continue;
            }
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            $addon = $addons->get($slug);
            $list[] = [
                'slug' => $slug,
                'label' => $addon ? $addon->label : str_replace('_', ' ', ucfirst($slug)),
                'qty' => $qty,
                'type' => $addon ? $addon->type : null,
            ];
        }

        return $list;
    }

    /**
     * Full BYO plan display for a user (features, capabilities, addons with labels).
     *
     * @return array{features: string[], capabilities: array<string, bool>, byo_addons_list: array}
     */
    public function getByoPlanDisplay(User $user): array
    {
        $addons = $this->getResolvedByoAddons($user);
        $resolved = $this->resolveByoPlanFromAddons($addons);

        return [
            'features' => $resolved['features'],
            'capabilities' => $resolved['capabilities'],
            'byo_addons_list' => $this->getByoAddonsDisplay($addons),
        ];
    }

    protected function getTierModel(string $tier): ?MemoraPricingTier
    {
        return MemoraPricingTier::getBySlug($tier);
    }

    protected function getConfig(string $tier, ?User $user = null): array
    {
        if ($tier === 'byo') {
            $byo = config('pricing.build_your_own', []);
            $base = [
                'storage_bytes' => $byo['base_storage_bytes'] ?? (5 * 1024 * 1024 * 1024),
                'max_revisions' => 0,
                'watermark_limit' => 1,
                'preset_limit' => 1,
                'set_limit_per_phase' => null,
                'features' => [],
                'capabilities' => ! $user ? array_fill_keys(self::$byoCapabilitySlugs, false) : [],
            ];

            if (! $user) {
                $byoConfig = MemoraByoConfig::getConfig();
                $base['project_limit'] = $byoConfig ? (int) $byoConfig->base_project_limit : ($byo['base_project_limit'] ?? 1);
                $base['collection_limit'] = null;
                $base['selection_limit'] = null;
                $base['proofing_limit'] = null;
                $base['raw_file_limit'] = null;

                return $base;
            }

            $byoConfig = MemoraByoConfig::getConfig();
            $baseSelection = $byoConfig ? (int) ($byoConfig->base_selection_limit ?? 0) : 0;
            $baseProofing = $byoConfig ? (int) ($byoConfig->base_proofing_limit ?? 0) : 0;
            $baseCollection = $byoConfig ? (int) ($byoConfig->base_collection_limit ?? 0) : 0;
            $baseProject = $byoConfig ? (int) $byoConfig->base_project_limit : (int) ($byo['base_project_limit'] ?? 1);
            $baseRawFile = $byoConfig ? (int) ($byoConfig->base_raw_file_limit ?? 0) : 0;
            $baseMaxRevisions = $byoConfig ? (int) ($byoConfig->base_max_revisions ?? 0) : 0;

            $byoAddons = $this->getResolvedByoAddons($user);

            if (! empty($byoAddons)) {
                $addons = MemoraByoAddon::whereIn('slug', array_keys($byoAddons))->get();
                foreach ($addons as $addon) {
                    $qty = (int) ($byoAddons[$addon->slug] ?? 1);
                    if ($qty <= 0) {
                        continue;
                    }
                    $baseSelection += (int) ($addon->selection_limit_granted ?? 0) * $qty;
                    $baseProofing += (int) ($addon->proofing_limit_granted ?? 0) * $qty;
                    $baseCollection += (int) ($addon->collection_limit_granted ?? 0) * $qty;
                    $baseProject += (int) ($addon->project_limit_granted ?? 0) * $qty;
                    $baseRawFile += (int) ($addon->raw_file_limit_granted ?? 0) * $qty;
                    $baseMaxRevisions += (int) ($addon->max_revisions_granted ?? 0) * $qty;
                }
            }

            $base['selection_limit'] = $baseSelection;
            $base['proofing_limit'] = $baseProofing;
            $base['collection_limit'] = $baseCollection;
            $base['project_limit'] = $baseProject;
            $base['raw_file_limit'] = $baseRawFile;
            $base['max_revisions'] = $baseMaxRevisions;
            $base['capabilities'] = $this->getCapabilitiesForByo($byoAddons);

            return $base;
        }

        $model = $this->getTierModel($tier);
        if ($model) {
            $fallback = config("pricing.tiers.{$tier}", []);

            return [
                'storage_bytes' => $model->storage_bytes,
                'project_limit' => $model->project_limit,
                'collection_limit' => $model->collection_limit,
                'selection_limit' => $model->selection_limit ?? $fallback['selection_limit'] ?? null,
                'proofing_limit' => $model->proofing_limit ?? $fallback['proofing_limit'] ?? null,
                'raw_file_limit' => $model->raw_file_limit ?? $fallback['raw_file_limit'] ?? null,
                'max_revisions' => $model->max_revisions,
                'watermark_limit' => $model->watermark_limit,
                'preset_limit' => $model->preset_limit,
                'set_limit_per_phase' => $model->set_limit_per_phase ?? $fallback['set_limit_per_phase'] ?? null,
                'features' => $model->features ?? [],
                'capabilities' => $model->capabilities ?? $fallback['capabilities'] ?? $this->defaultCapabilities($tier),
            ];
        }

        $config = config("pricing.tiers.{$tier}", []);

        return array_merge($config, [
            'selection_limit' => $config['selection_limit'] ?? null,
            'proofing_limit' => $config['proofing_limit'] ?? null,
            'raw_file_limit' => $config['raw_file_limit'] ?? null,
            'set_limit_per_phase' => $config['set_limit_per_phase'] ?? null,
            'capabilities' => $config['capabilities'] ?? $this->defaultCapabilities($tier),
        ]);
    }

    public function getRawFileLimit(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);

        return $config['raw_file_limit'] ?? null;
    }

    protected function defaultCapabilities(string $tier): array
    {
        $all = [
            'homepage_enabled' => true,
            'branding_editable' => true,
            'social_links_enabled' => true,
            'collection_display_enabled' => true,
            'photo_quality_enabled' => true,
            'legal_documents_enabled' => true,
            'support_24_7' => $tier === 'business',
        ];

        return $all;
    }

    public function getSetLimitPerPhase(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);
        $v = $config['set_limit_per_phase'] ?? null;

        return $v !== null ? (int) $v : null;
    }

    public function getCapabilities(?User $user = null): array
    {
        $config = $this->getTierConfig($user);

        return $config['capabilities'] ?? $this->defaultCapabilities($this->getTier($user));
    }

    public function getCapability(string $key, ?User $user = null): bool
    {
        $caps = $this->getCapabilities($user);

        return (bool) ($caps[$key] ?? false);
    }

    public function getStorageLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);

        if ($tier === 'byo' && $user) {
            $base = (int) (config('pricing.build_your_own.base_storage_bytes') ?? (5 * 1024 * 1024 * 1024));
            $byoAddons = $this->getResolvedByoAddons($user);
            if (! empty($byoAddons)) {
                $storageAddons = MemoraByoAddon::whereIn('slug', array_keys($byoAddons))->where('type', 'storage')->get();
                foreach ($storageAddons as $addon) {
                    $qty = (int) ($byoAddons[$addon->slug] ?? 1);
                    if ($qty > 0 && $addon->storage_bytes !== null) {
                        $base += (int) $addon->storage_bytes * $qty;
                    }
                }
            }

            return $base;
        }

        $config = $this->getConfig($tier, $user);
        $bytes = $config['storage_bytes'] ?? null;
        if ($bytes !== null) {
            return (int) $bytes;
        }

        if ($tier === 'business') {
            return config('pricing.business_storage_soft_cap_bytes');
        }

        return config('quotas.upload.per_user.default');
    }

    public function getProjectLimit(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);

        return $config['project_limit'] ?? null;
    }

    public function getCollectionLimit(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);

        return $config['collection_limit'] ?? null;
    }

    public function getSelectionLimit(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);

        return $config['selection_limit'] ?? null;
    }

    public function getProofingLimit(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);

        return $config['proofing_limit'] ?? null;
    }

    public function getMaxRevisions(?User $user = null): int
    {
        $config = $this->getTierConfig($user);

        return (int) ($config['max_revisions'] ?? 0);
    }

    public function getWatermarkLimit(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);

        return $config['watermark_limit'] ?? null;
    }

    public function getPresetLimit(?User $user = null): ?int
    {
        $config = $this->getTierConfig($user);

        return $config['preset_limit'] ?? null;
    }

    public function hasFeature(string $feature, ?User $user = null): bool
    {
        $features = $this->getFeatures($user);

        return in_array($feature, $features, true);
    }

    public function canUseProofing(?User $user = null): bool
    {
        return $this->hasFeature('proofing', $user);
    }

    public function canUseRawFiles(?User $user = null): bool
    {
        return $this->hasFeature('raw_files', $user);
    }

    public function getTierConfig(?User $user = null): array
    {
        $tier = $this->getTier($user);

        return $this->getConfig($tier, $user);
    }
}
