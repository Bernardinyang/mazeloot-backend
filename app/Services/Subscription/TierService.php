<?php

namespace App\Services\Subscription;

use App\Models\User;

class TierService
{
    public function getTier(?User $user = null): string
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return 'starter';
        }

        $tier = $user->memora_tier ?? 'starter';
        $validTiers = ['starter', 'pro', 'studio', 'business'];

        return in_array($tier, $validTiers, true) ? $tier : 'starter';
    }

    public function getStorageLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = config("pricing.tiers.{$tier}", []);

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
        $config = config("pricing.tiers.{$tier}", []);

        return $config['project_limit'] ?? null;
    }

    public function getCollectionLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = config("pricing.tiers.{$tier}", []);

        return $config['collection_limit'] ?? null;
    }

    public function getMaxRevisions(?User $user = null): int
    {
        $tier = $this->getTier($user);
        $config = config("pricing.tiers.{$tier}", []);

        return (int) ($config['max_revisions'] ?? 0);
    }

    public function getWatermarkLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = config("pricing.tiers.{$tier}", []);

        return $config['watermark_limit'] ?? null;
    }

    public function getPresetLimit(?User $user = null): ?int
    {
        $tier = $this->getTier($user);
        $config = config("pricing.tiers.{$tier}", []);

        return $config['preset_limit'] ?? null;
    }

    public function hasFeature(string $feature, ?User $user = null): bool
    {
        $tier = $this->getTier($user);
        $config = config("pricing.tiers.{$tier}", []);
        $features = $config['features'] ?? [];

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

        return config("pricing.tiers.{$tier}", []);
    }
}
