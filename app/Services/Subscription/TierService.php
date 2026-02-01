<?php

namespace App\Services\Subscription;

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

        $tier = $user->memora_tier ?? 'starter';
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

        $config = $this->getConfig($tier);
        $features = $config['features'] ?? [];

        return is_array($features) ? $features : [];
    }

    public function getRecommendedTierForFeature(string $feature): ?string
    {
        return self::$recommendedTierForFeature[$feature] ?? null;
    }

    protected function getFeaturesForByo(User $user): array
    {
        $features = ['selection', 'collection'];

        $subscription = MemoraSubscription::where('user_uuid', $user->uuid)
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();

        if (! $subscription?->metadata) {
            return $features;
        }

        $byoAddons = $subscription->metadata['byo_addons'] ?? [];
        if (is_string($byoAddons)) {
            $byoAddons = json_decode($byoAddons, true) ?? [];
        }

        if (! empty($byoAddons['proofing'])) {
            $features[] = 'proofing';
        }
        if (! empty($byoAddons['raw_files'])) {
            $features[] = 'raw_files';
        }

        return $features;
    }

    protected function getTierModel(string $tier): ?MemoraPricingTier
    {
        return MemoraPricingTier::getBySlug($tier);
    }

    protected function getConfig(string $tier): array
    {
        if ($tier === 'byo') {
            $byo = config('pricing.build_your_own', []);

            return [
                'storage_bytes' => $byo['base_storage_bytes'] ?? (5 * 1024 * 1024 * 1024),
                'project_limit' => $byo['base_project_limit'] ?? 3,
                'collection_limit' => null,
                'max_revisions' => 0,
                'watermark_limit' => 1,
                'preset_limit' => 1,
                'features' => [],
            ];
        }

        $model = $this->getTierModel($tier);
        if ($model) {
            return [
                'storage_bytes' => $model->storage_bytes,
                'project_limit' => $model->project_limit,
                'collection_limit' => $model->collection_limit,
                'max_revisions' => $model->max_revisions,
                'watermark_limit' => $model->watermark_limit,
                'preset_limit' => $model->preset_limit,
                'features' => $model->features ?? [],
            ];
        }

        return config("pricing.tiers.{$tier}", []);
    }

    public function getStorageLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = $this->getConfig($tier);

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
        $tier = $this->getTier($user);
        $config = $this->getConfig($tier);

        return $config['project_limit'] ?? null;
    }

    public function getCollectionLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = $this->getConfig($tier);

        return $config['collection_limit'] ?? null;
    }

    public function getMaxRevisions(?User $user = null): int
    {
        $tier = $this->getTier($user);
        $config = $this->getConfig($tier);

        return (int) ($config['max_revisions'] ?? 0);
    }

    public function getWatermarkLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = $this->getConfig($tier);

        return $config['watermark_limit'] ?? null;
    }

    public function getPresetLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = $this->getConfig($tier);

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

        return $this->getConfig($tier);
    }
}
