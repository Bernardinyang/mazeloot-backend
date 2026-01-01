<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\UpdateBrandingSettingsRequest;
use App\Domains\Memora\Requests\V1\UpdateEmailSettingsRequest;
use App\Domains\Memora\Requests\V1\UpdateHomepageSettingsRequest;
use App\Domains\Memora\Requests\V1\UpdatePreferenceSettingsRequest;
use App\Domains\Memora\Resources\V1\SettingsResource;
use App\Domains\Memora\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

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
        $settings = $this->settingsService->updateBranding($request->validated());
        $settings->load(['logo', 'favicon']);

        return ApiResponse::success(new SettingsResource($settings));
    }

    /**
     * Update preference settings
     */
    public function updatePreference(UpdatePreferenceSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsService->updatePreference($request->validated());

        return ApiResponse::success(new SettingsResource($settings));
    }

    /**
     * Update homepage settings
     */
    public function updateHomepage(UpdateHomepageSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsService->updateHomepage($request->validated());

        return ApiResponse::success(new SettingsResource($settings));
    }

    /**
     * Update email settings
     */
    public function updateEmail(UpdateEmailSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsService->updateEmailSettings($request->validated());

        return ApiResponse::success(new SettingsResource($settings));
    }
}
