<?php

namespace App\Http\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreSocialMediaPlatformRequest;
use App\Domains\Memora\Requests\V1\UpdateSocialMediaPlatformRequest;
use App\Http\Controllers\Controller;
use App\Resources\V1\SocialMediaPlatformResource;
use App\Services\SocialMediaPlatformService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

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
        return ApiResponse::success(new SocialMediaPlatformResource($platform), 201);
    }

    public function update(UpdateSocialMediaPlatformRequest $request, string $id): JsonResponse
    {
        $platform = $this->platformService->update($id, $request->validated());
        return ApiResponse::success(new SocialMediaPlatformResource($platform));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->platformService->delete($id);
        return ApiResponse::success(null, 204);
    }

    public function toggle(string $id): JsonResponse
    {
        $platform = $this->platformService->toggleActive($id);
        return ApiResponse::success(new SocialMediaPlatformResource($platform));
    }
}
