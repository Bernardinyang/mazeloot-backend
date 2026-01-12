<?php

namespace App\Services\Product;

use App\Domains\Memora\Models\MemoraSettings;
use App\Domains\Memora\Services\SettingsService;
use Illuminate\Support\Facades\DB;

class MemoraSetupHandler implements ProductSetupService
{
    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Initialize Memora setup for a user
     */
    public function setup(string $userUuid, array $data = []): MemoraSettings
    {
        $domain = $data['domain'] ?? null;

        // Validate domain if provided
        if ($domain) {
            $this->validateDomain($domain, $userUuid);
        }

        // Initialize default settings with domain
        $settings = $this->settingsService->initializeDefaults($userUuid, $domain);

        // Update domain if provided
        if ($domain) {
            $settings->update(['branding_domain' => $domain]);
        }

        return $settings;
    }

    /**
     * Validate domain format and availability
     */
    protected function validateDomain(string $domain, string $userUuid): void
    {
        // Format validation
        if (!preg_match('/^[a-z0-9-]+$/', $domain)) {
            throw new \InvalidArgumentException('Domain can only contain lowercase letters, numbers, and hyphens.');
        }

        if (strlen($domain) < 3 || strlen($domain) > 63) {
            throw new \InvalidArgumentException('Domain must be between 3 and 63 characters.');
        }

        if ($domain[0] === '-' || $domain[strlen($domain) - 1] === '-') {
            throw new \InvalidArgumentException('Domain cannot start or end with a hyphen.');
        }

        // Reserved words
        $reserved = ['admin', 'api', 'www', 'mail', 'ftp', 'localhost', 'test', 'staging', 'dev', 'app'];
        if (in_array(strtolower($domain), $reserved)) {
            throw new \InvalidArgumentException('This domain is reserved and cannot be used.');
        }

        // Check availability
        $isTaken = DB::table('user_product_preferences')
            ->where('domain', $domain)
            ->where('user_uuid', '!=', $userUuid)
            ->exists();

        if ($isTaken) {
            throw new \InvalidArgumentException('This domain is already taken.');
        }
    }
}
