<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraSettings;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Auth;

class SettingsService
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Initialize default settings for a user
     */
    public function initializeDefaults(string $userUuid): MemoraSettings
    {
        return MemoraSettings::firstOrCreate(
            ['user_uuid' => $userUuid],
            [
                'branding_show_mazeloot_branding' => true,
                'preference_filename_display' => 'show',
                'preference_search_engine_visibility' => 'homepage-only',
                'preference_sharpening_level' => 'optimal',
                'preference_raw_photo_support' => false,
                'preference_enable_cookie_banner' => false,
                'preference_language' => 'en',
                'preference_timezone' => 'UTC',
                'homepage_status' => true,
                'homepage_info' => json_encode(['biography', 'socialLinks']),
            ]
        );
    }

    /**
     * Get or create settings for the authenticated user
     */
    public function getSettings(): MemoraSettings
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraSettings::firstOrCreate(
            ['user_uuid' => $user->uuid],
            [
                'branding_show_mazeloot_branding' => true,
                'preference_filename_display' => 'show',
                'preference_search_engine_visibility' => 'homepage-only',
                'preference_sharpening_level' => 'optimal',
                'preference_language' => 'en',
                'preference_timezone' => 'UTC',
                'homepage_status' => true,
            ]
        );
    }

    /**
     * Update branding settings
     */
    public function updateBranding(array $data): MemoraSettings
    {
        $settings = $this->getSettings();

        $updateData = [];

        if (isset($data['customDomain'])) {
            $updateData['branding_custom_domain'] = $data['customDomain'];
        }

        if (isset($data['showMazelootBranding'])) {
            $updateData['branding_show_mazeloot_branding'] = $data['showMazelootBranding'];
        }

        if (isset($data['logoUuid'])) {
            $updateData['branding_logo_uuid'] = $data['logoUuid'];
        }

        if (isset($data['faviconUuid'])) {
            $updateData['branding_favicon_uuid'] = $data['faviconUuid'];
        }

        $settings->update($updateData);
        $settings->refresh();

        return $settings;
    }

    /**
     * Update preference settings
     */
    public function updatePreference(array $data): MemoraSettings
    {
        $settings = $this->getSettings();

        $updateData = [];

        $preferenceFields = [
            'filenameDisplay' => 'preference_filename_display',
            'searchEngineVisibility' => 'preference_search_engine_visibility',
            'sharpeningLevel' => 'preference_sharpening_level',
            'rawPhotoSupport' => 'preference_raw_photo_support',
            'termsOfService' => 'preference_terms_of_service',
            'privacyPolicy' => 'preference_privacy_policy',
            'enableCookieBanner' => 'preference_enable_cookie_banner',
            'language' => 'preference_language',
            'timezone' => 'preference_timezone',
        ];

        foreach ($preferenceFields as $key => $dbKey) {
            if (array_key_exists($key, $data)) {
                $updateData[$dbKey] = $data[$key];
            }
        }

        $settings->update($updateData);
        $settings->refresh();

        return $settings;
    }

    /**
     * Update homepage settings
     */
    public function updateHomepage(array $data): MemoraSettings
    {
        $settings = $this->getSettings();

        $updateData = [];

        if (array_key_exists('status', $data)) {
            $updateData['homepage_status'] = $data['status'];
        }

        if (array_key_exists('password', $data)) {
            $updateData['homepage_password'] = $data['password'];
        }

        if (array_key_exists('biography', $data)) {
            $updateData['homepage_biography'] = $data['biography'];
        }

        if (array_key_exists('info', $data)) {
            $updateData['homepage_info'] = is_array($data['info']) ? $data['info'] : [];
        }

        $settings->update($updateData);
        $settings->refresh();

        return $settings;
    }

    /**
     * Update email settings
     */
    public function updateEmailSettings(array $data): MemoraSettings
    {
        $settings = $this->getSettings();

        $updateData = [];

        if (array_key_exists('fromName', $data)) {
            $updateData['email_from_name'] = $data['fromName'];
        }

        if (array_key_exists('fromAddress', $data)) {
            $updateData['email_from_address'] = $data['fromAddress'];
        }

        if (array_key_exists('replyTo', $data)) {
            $updateData['email_reply_to'] = $data['replyTo'];
        }

        $settings->update($updateData);
        $settings->refresh();

        return $settings;
    }
}
