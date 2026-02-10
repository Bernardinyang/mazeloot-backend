<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\UpdateBrandingSettingsRequest;
use App\Domains\Memora\Requests\V1\UpdateEmailSettingsRequest;
use App\Domains\Memora\Requests\V1\UpdateHomepageSettingsRequest;
use App\Domains\Memora\Requests\V1\UpdatePreferenceSettingsRequest;
use App\Domains\Memora\Resources\V1\SettingsResource;
use App\Domains\Memora\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Services\Subscription\TierService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService,
        protected TierService $tierService
    ) {}

    /**
     * Get user settings
     */
    public function index(): JsonResponse
    {
        $settings = $this->settingsService->getSettings();
        $settings->load(['logo', 'favicon']);

        return ApiResponse::success(new SettingsResource($settings));
    }

    /**
     * Update branding settings
     */
    public function updateBranding(UpdateBrandingSettingsRequest $request): JsonResponse
    {
        if (! $this->tierService->getCapability('branding_editable', $request->user())) {
            return ApiResponse::errorForbidden('Brand Assets, Domain Settings, and Mazeloot Branding cannot be updated on your plan.');
        }
        $settings = $this->settingsService->updateBranding($request->validated());
        $settings->load(['logo', 'favicon']);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'settings_branding_updated',
                $settings,
                'Branding settings updated',
                null,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log settings activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SettingsResource($settings));
    }

    /**
     * Update preference settings
     */
    public function updatePreference(UpdatePreferenceSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if (! $this->tierService->getCapability('legal_documents_enabled', $user)) {
            if (array_key_exists('termsOfService', $data) || array_key_exists('privacyPolicy', $data)) {
                return ApiResponse::errorForbidden('Legal documents cannot be updated on your plan.');
            }
        }
        if (! $this->tierService->getCapability('photo_quality_enabled', $user)) {
            if (array_key_exists('sharpeningLevel', $data)) {
                return ApiResponse::errorForbidden('Photo quality settings cannot be updated on your plan.');
            }
        }
        if (! $this->tierService->getCapability('collection_display_enabled', $user)) {
            if (array_key_exists('filenameDisplay', $data) || array_key_exists('searchEngineVisibility', $data)) {
                return ApiResponse::errorForbidden('Collection display settings cannot be updated on your plan.');
            }
        }

        if (! $this->tierService->getCapability('legal_documents_enabled', $user)) {
            unset($data['termsOfService'], $data['privacyPolicy']);
        }
        if (! $this->tierService->getCapability('photo_quality_enabled', $user)) {
            unset($data['sharpeningLevel']);
        }
        if (! $this->tierService->getCapability('collection_display_enabled', $user)) {
            unset($data['filenameDisplay'], $data['searchEngineVisibility']);
        }
        $settings = $this->settingsService->updatePreference($data);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'settings_preference_updated',
                $settings,
                'Preference settings updated',
                null,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log settings activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SettingsResource($settings));
    }

    /**
     * Update homepage settings
     */
    public function updateHomepage(UpdateHomepageSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! $this->tierService->getCapability('homepage_enabled', $request->user()) && ! empty($data['status'])) {
            return ApiResponse::errorForbidden('Homepage is not available on your plan.');
        }

        // Prevent updates when homepage is disabled (except enabling it)
        $currentSettings = $this->settingsService->getSettings();
        $isHomepageDisabled = ! ($currentSettings->homepage_status ?? false);

        if ($isHomepageDisabled) {
            // Only allow enabling the homepage, reject all other updates
            if (! isset($data['status']) || ! $data['status']) {
                return ApiResponse::errorForbidden('Homepage is disabled. Enable it first to update settings.');
            }
        }

        $settings = $this->settingsService->updateHomepage($data);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'settings_homepage_updated',
                $settings,
                'Homepage settings updated',
                null,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log settings activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SettingsResource($settings));
    }

    /**
     * Update email settings
     */
    public function updateEmail(UpdateEmailSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsService->updateEmailSettings($request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'settings_email_updated',
                $settings,
                'Email settings updated',
                null,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log settings activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SettingsResource($settings));
    }
}
