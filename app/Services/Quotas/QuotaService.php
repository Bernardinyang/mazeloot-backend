<?php

namespace App\Services\Quotas;

use App\Services\Storage\UserStorageService;
use App\Services\Upload\Exceptions\UploadException;

class QuotaService
{
    public function __construct(
        protected UserStorageService $storageService
    ) {}

    /**
     * Check if upload quota allows the file size
     *
     * @param  int  $fileSize  Size in bytes
     * @param  string|null  $domain  Domain name (e.g., 'memora')
     * @param  int|null  $userId  User ID
     *
     * @throws UploadException
     */
    public function checkUploadQuota(int $fileSize, ?string $domain = null, ?int $userId = null): void
    {
        $quota = $this->getUploadQuota($domain, $userId);

        if ($quota === null) {
            // No quota limits
            return;
        }

        $used = $this->getUsedQuota($domain, $userId);

        if (($used + $fileSize) > $quota) {
            throw UploadException::quotaExceeded(
                'Upload quota exceeded. Available: '.number_format(($quota - $used) / 1024 / 1024, 2).' MB'
            );
        }
    }

    /**
     * Get upload quota for domain/user
     *
     * @return int|null Quota in bytes, null if unlimited
     */
    public function getUploadQuota(?string $domain = null, ?int $userId = null): ?int
    {
        $config = config('upload.quota', []);

        $baseQuota = null;

        // Check per-domain quota first
        if ($domain && isset($config['per_domain'][$domain])) {
            $baseQuota = $config['per_domain'][$domain];
        }
        // Check per-user quota
        elseif ($userId && isset($config['per_user'][$userId])) {
            $baseQuota = $config['per_user'][$userId];
        }
        // Check default user quota
        elseif (isset($config['per_user']['default'])) {
            $baseQuota = $config['per_user']['default'];
        }

        if (! $baseQuota) {
            return null;
        }

        // Apply early access storage multiplier
        if ($userId) {
            $user = \App\Models\User::find($userId);
        } else {
            $user = auth()->user();
        }

        if ($user) {
            $multiplier = $user->getStorageMultiplier();
            if ($multiplier > 1.0) {
                return (int) round($baseQuota * $multiplier);
            }
        }

        return $baseQuota;
    }

    /**
     * Get used quota
     *
     * @return int Used quota in bytes
     */
    protected function getUsedQuota(?string $domain = null, ?int $userId = null): int
    {
        if (! $userId) {
            $user = auth()->user();
            if (! $user) {
                return 0;
            }
            $userUuid = $user->uuid;
        } else {
            $user = \App\Models\User::find($userId);
            if (! $user) {
                return 0;
            }
            $userUuid = $user->uuid;
        }

        return $this->storageService->getTotalStorageUsed($userUuid);
    }
}
