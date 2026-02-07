<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreSocialLinkRequest;
use App\Domains\Memora\Requests\V1\UpdateSocialLinkRequest;
use App\Domains\Memora\Resources\V1\SocialLinkResource;
use App\Domains\Memora\Services\SocialLinkService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
    protected SocialLinkService $socialLinkService;

    public function __construct(SocialLinkService $socialLinkService)
    {
        $this->socialLinkService = $socialLinkService;
    }

    /**
     * Get user's social links
     */
    public function index(): JsonResponse
    {
        $links = $this->socialLinkService->getByUser();

        return ApiResponse::success(SocialLinkResource::collection($links));
    }

    /**
     * Get available active platforms for selection
     */
    public function getPlatforms(): JsonResponse
    {
        $platforms = $this->socialLinkService->getAvailablePlatforms();

        return ApiResponse::success(\App\Resources\V1\SocialMediaPlatformResource::collection($platforms));
    }

    /**
     * Create a social link
     */
    public function store(StoreSocialLinkRequest $request): JsonResponse
    {
        if (! app(\App\Services\Subscription\TierService::class)->getCapability('social_links_enabled', $request->user())) {
            return ApiResponse::errorForbidden('Adding social links is not available on your plan.');
        }
        $link = $this->socialLinkService->create($request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'created',
                $link,
                'Social link created',
                ['social_link_uuid' => $link->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social link activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SocialLinkResource($link), 201);
    }

    /**
     * Update a social link
     */
    public function update(UpdateSocialLinkRequest $request, string $id): JsonResponse
    {
        $link = $this->socialLinkService->update($id, $request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $link,
                'Social link updated',
                ['social_link_uuid' => $link->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social link activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new SocialLinkResource($link));
    }

    /**
     * Delete a social link
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->socialLinkService->delete($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'deleted',
                null,
                'Social link deleted',
                ['social_link_uuid' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social link activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder social links
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['uuid'],
        ]);

        $links = $this->socialLinkService->reorder($request->input('order'));

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'social_links_reordered',
                null,
                'Social links reordered',
                ['count' => count($request->input('order'))],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log social link activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(SocialLinkResource::collection($links));
    }
}
