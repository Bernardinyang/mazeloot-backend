<?php

namespace App\Services\Quotas;

use App\Services\Upload\Exceptions\UploadException;

class QuotaService
{
    /**
     * Check if upload quota allows the file size
     *
     * @param int $fileSize Size in bytes
     * @param string|null $domain Domain name (e.g., 'memora')
     * @param int|null $userId User ID
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
                "Upload quota exceeded. Available: " . number_format(($quota - $used) / 1024 / 1024, 2) . " MB"
            );
        }
    }

    /**
     * Get upload quota for domain/user
     *
     * @param string|null $domain
     * @param int|null $userId
     * @return int|null Quota in bytes, null if unlimited
     */
    protected function getUploadQuota(?string $domain = null, ?int $userId = null): ?int
    {
        $config = config('upload.quota', []);

        // Check per-domain quota first
        if ($domain && isset($config['per_domain'][$domain])) {
            return $config['per_domain'][$domain];
        }

        // Check per-user quota
        if ($userId && isset($config['per_user'][$userId])) {
            return $config['per_user'][$userId];
        }

        // Check default user quota
        if (isset($config['per_user']['default'])) {
            return $config['per_user']['default'];
        }

        return null;
    }

    /**
     * Get used quota (placeholder - would integrate with storage tracking)
     *
     * @param string|null $domain
     * @param int|null $userId
     * @return int Used quota in bytes
     */
    protected function getUsedQuota(?string $domain = null, ?int $userId = null): int
    {
        // TODO: Implement actual quota tracking
        // This would query the database/storage to get actual usage
        return 0;
    }
}
