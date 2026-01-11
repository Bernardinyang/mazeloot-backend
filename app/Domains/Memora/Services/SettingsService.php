<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraSettings;
use App\Services\Notification\NotificationService;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Auth;

class SettingsService
{
    protected UploadService $uploadService;
    protected NotificationService $notificationService;

    public function __construct(
        UploadService $uploadService,
        NotificationService $notificationService
    ) {
        $this->uploadService = $uploadService;
        $this->notificationService = $notificationService;
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

        if (array_key_exists('name', $data)) {
            $updateData['branding_name'] = $data['name'];
        }

        if (array_key_exists('supportEmail', $data)) {
            $updateData['branding_support_email'] = $data['supportEmail'];
        }

        if (array_key_exists('supportPhone', $data)) {
            $updateData['branding_support_phone'] = $data['supportPhone'];
        }

        if (array_key_exists('website', $data)) {
            $updateData['branding_website'] = $data['website'];
        }

        if (array_key_exists('location', $data)) {
            $updateData['branding_location'] = $data['location'];
        }

        if (array_key_exists('tagline', $data)) {
            $updateData['branding_tagline'] = $data['tagline'];
        }

        if (array_key_exists('description', $data)) {
            $updateData['branding_description'] = $data['description'];
        }

        if (array_key_exists('addressStreet', $data)) {
            $updateData['branding_address_street'] = $data['addressStreet'];
        }

        if (array_key_exists('addressCity', $data)) {
            $updateData['branding_address_city'] = $data['addressCity'];
        }

        if (array_key_exists('addressState', $data)) {
            $updateData['branding_address_state'] = $data['addressState'];
        }

        if (array_key_exists('addressZip', $data)) {
            $updateData['branding_address_zip'] = $data['addressZip'];
        }

        if (array_key_exists('addressCountry', $data)) {
            $updateData['branding_address_country'] = $data['addressCountry'];
        }

        if (array_key_exists('businessHours', $data)) {
            $updateData['branding_business_hours'] = $data['businessHours'];
        }

        if (array_key_exists('contactName', $data)) {
            $updateData['branding_contact_name'] = $data['contactName'];
        }

        if (array_key_exists('taxVatId', $data)) {
            $updateData['branding_tax_vat_id'] = $data['taxVatId'];
        }

        if (array_key_exists('foundedYear', $data)) {
            $updateData['branding_founded_year'] = $data['foundedYear'];
        }

        if (array_key_exists('industry', $data)) {
            $updateData['branding_industry'] = $data['industry'];
        }

        $settings->update($updateData);
        $settings->refresh();

        // Create notification
        $user = Auth::user();
        if ($user) {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'settings_branding_updated',
                'Branding Settings Updated',
                'Your branding settings have been updated successfully.',
                'Your branding configuration has been saved and will be applied to your collections.',
                '/memora/settings/branding'
            );
        }

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
            'usePreviewWatermark' => 'preference_use_preview_watermark',
        ];

        foreach ($preferenceFields as $key => $dbKey) {
            if (array_key_exists($key, $data)) {
                $updateData[$dbKey] = $data[$key];
            }
        }

        $settings->update($updateData);
        $settings->refresh();

        // Create notification
        $user = Auth::user();
        if ($user) {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'settings_preference_updated',
                'Preference Settings Updated',
                'Your preference settings have been updated successfully.',
                'Your preference configuration has been saved and will be applied to your collections.',
                '/memora/settings/preference'
            );
        }

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

        if (array_key_exists('slideshowEnabled', $data)) {
            $updateData['homepage_slideshow_enabled'] = $data['slideshowEnabled'];
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

        // Create notification
        $user = Auth::user();
        if ($user) {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'settings_email_updated',
                'Email Settings Updated',
                'Your email settings have been updated successfully.',
                'Your email configuration has been saved and will be used for notifications.',
                '/memora/settings/email'
            );
        }

        return $settings;
    }
}
