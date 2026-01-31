<?php

namespace App\Services\Quotas;

use App\Models\User;
use App\Services\Storage\UserStorageService;
use App\Services\Subscription\TierService;
use App\Services\Upload\Exceptions\UploadException;

class QuotaService
{
    public function __construct(
        protected UserStorageService $storageService,
        protected TierService $tierService
    ) {}

    /**
     * Check if upload quota allows the file size
     *
     * @param  int  $fileSize  Size in bytes
     * @param  string|null  $domain  Domain name (e.g., 'memora')
     * @param  int|string|null  $userIdOrUuid  User ID (int) or UUID (string)
     *
     * @throws UploadException
     */
    public function checkUploadQuota(int $fileSize, ?string $domain = null, int|string|null $userIdOrUuid = null): void
    {
        $quota = $this->getUploadQuota($domain, $userIdOrUuid);

        if ($quota === null) {
            return;
        }

        $used = $this->getUsedQuota($domain, $userIdOrUuid);

        if (($used + $fileSize) > $quota) {
            throw UploadException::quotaExceeded(
                'Upload quota exceeded. Available: '.number_format(($quota - $used) / 1024 / 1024, 2).' MB'
            );
        }
    }

    /**
     * Get upload quota for domain/user. Uses tier-based limits when user has Memora tier.
     *
     * @param  int|string|null  $userIdOrUuid  User ID (int) or UUID (string)
     * @return int|null Quota in bytes, null if unlimited
     */
    public function getUploadQuota(?string $domain = null, int|string|null $userIdOrUuid = null): ?int
    {
        $user = $this->resolveUser($userIdOrUuid);

        if ($user) {
            $tierLimit = $this->tierService->getStorageLimit($user);
            if ($tierLimit !== null) {
                $earlyAccessMultiplier = method_exists($user, 'getStorageMultiplier') ? $user->getStorageMultiplier() : 1.0;
                if ($earlyAccessMultiplier > 1.0) {
                    return (int) round($tierLimit * $earlyAccessMultiplier);
                }

                return $tierLimit;
            }
        }

        $config = config('upload.quota', []);
        $baseQuota = $config['per_domain'][$domain] ?? $config['per_user']['default'] ?? null;

        if (! $baseQuota) {
            return null;
        }

        if ($user && method_exists($user, 'getStorageMultiplier')) {
            $multiplier = $user->getStorageMultiplier();
            if ($multiplier > 1.0) {
                return (int) round($baseQuota * $multiplier);
            }
        }

        return $baseQuota;
    }

    protected function getUsedQuota(?string $domain = null, int|string|null $userIdOrUuid = null): int
    {
        $user = $this->resolveUser($userIdOrUuid);

        if (! $user) {
            return 0;
        }

        return $this->storageService->getTotalStorageUsed($user->uuid);
    }

    protected function resolveUser(int|string|null $userIdOrUuid): ?User
    {
        if ($userIdOrUuid === null) {
            return auth()->user();
        }

        if (is_string($userIdOrUuid)) {
            return User::find($userIdOrUuid);
        }

        return User::where('id', $userIdOrUuid)->first();
    }
}
