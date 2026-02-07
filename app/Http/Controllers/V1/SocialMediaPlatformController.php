<?php

namespace App\Http\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreSocialMediaPlatformRequest;
use App\Domains\Memora\Requests\V1\UpdateSocialMediaPlatformRequest;
use App\Http\Controllers\Controller;
use App\Resources\V1\SocialMediaPlatformResource;
use App\Services\SocialMediaPlatformService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialMediaPlatformController extends Controller
{
    protected SocialMediaPlatformService $platformService;

    public function __construct(SocialMediaPlatformService $platformService)
    {
        $this->platformService = $platformService;
    }

    public function index(): JsonResponse
    {
        $platforms = $this->platformService->getAll();

        return ApiResponse::success(SocialMediaPlatformResource::collection($platforms));
    }

    public function store(StoreSocialMediaPlatformRequest $request): JsonResponse
    {
        $platform = $this->platformService->create($request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'created',
                $platform,
                'Social media platform created',
                ['platform_id' => $platform->id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social media platform activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SocialMediaPlatformResource($platform), 201);
    }

    public function update(UpdateSocialMediaPlatformRequest $request, string $id): JsonResponse
    {
        $platform = $this->platformService->update($id, $request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $platform,
                'Social media platform updated',
                ['platform_id' => $platform->id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social media platform activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SocialMediaPlatformResource($platform));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->platformService->delete($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'deleted',
                null,
                'Social media platform deleted',
                ['platform_id' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social media platform activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(null, 204);
    }

    public function toggle(Request $request, string $id): JsonResponse
    {
        $platform = $this->platformService->toggleActive($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'social_media_platform_toggled',
                $platform,
                'Social media platform toggled',
                ['platform_id' => $platform->id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social media platform activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SocialMediaPlatformResource($platform));
    }
}
