<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraSettings;
use App\Models\User;

class DomainService
{
    /**
     * Validate domain availability and format.
     *
     * @param  string  $domain
     * @param  string|null  $excludeUserUuid  User UUID to exclude from availability check
     * @return array{available: bool, message: string}
     */
    public function validateDomain(string $domain, ?string $excludeUserUuid = null): array
    {
        // Normalize domain (lowercase, trim)
        $domain = strtolower(trim($domain));

        // Validate format: alphanumeric, hyphens, underscores, 3-50 chars
        if (!preg_match('/^[a-z0-9_-]{3,50}$/', $domain)) {
            return [
                'available' => false,
                'message' => 'Domain must be 3-50 characters and contain only letters, numbers, hyphens, and underscores.',
            ];
        }

        // Check if domain is available
        if (!$this->isDomainAvailable($domain, $excludeUserUuid)) {
            return [
                'available' => false,
                'message' => 'This domain is already taken. Please choose another.',
            ];
        }

        return [
            'available' => true,
            'message' => 'Domain is available.',
        ];
    }

    /**
     * Check if domain is available.
     *
     * @param  string  $domain
     * @param  string|null  $excludeUserUuid  User UUID to exclude from availability check
     * @return bool
     */
    public function isDomainAvailable(string $domain, ?string $excludeUserUuid = null): bool
    {
        $domain = strtolower(trim($domain));

        $query = MemoraSettings::where('branding_domain', $domain);
        
        // Exclude current user's domain if provided
        if ($excludeUserUuid) {
            $query->where('user_uuid', '!=', $excludeUserUuid);
        }

        return !$query->exists();
    }

    /**
     * Save domain to user's Memora settings.
     *
     * @param  User  $user
     * @param  string  $domain
     * @return void
     */
    public function saveDomain(User $user, string $domain): void
    {
        $domain = strtolower(trim($domain));

        // Validate domain before saving, excluding current user's existing domain
        $validation = $this->validateDomain($domain, $user->uuid);
        if (!$validation['available']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        // Get or create Memora settings
        $settings = MemoraSettings::firstOrCreate(
            ['user_uuid' => $user->uuid],
            []
        );

        // Update domain
        $settings->update(['branding_domain' => $domain]);
    }
}
