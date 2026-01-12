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
        $link = $this->socialLinkService->create($request->validated());

        return ApiResponse::success(new SocialLinkResource($link), 201);
    }

    /**
     * Update a social link
     */
    public function update(UpdateSocialLinkRequest $request, string $id): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $link = $this->socialLinkService->update($id, $request->validated());

        return ApiResponse::success(new SocialLinkResource($link));
    }

    /**
     * Delete a social link
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $this->socialLinkService->delete($id);

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

        return ApiResponse::success(SocialLinkResource::collection($links));
    }
}
