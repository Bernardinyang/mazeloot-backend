<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreWatermarkRequest;
use App\Domains\Memora\Requests\V1\UpdateWatermarkRequest;
use App\Domains\Memora\Resources\V1\WatermarkResource;
use App\Domains\Memora\Services\WatermarkService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ImageUploadRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatermarkController extends Controller
{
    protected WatermarkService $watermarkService;

    public function __construct(WatermarkService $watermarkService)
    {
        $this->watermarkService = $watermarkService;
    }

    /**
     * Get user's watermarks
     */
    public function index(): JsonResponse
    {
        $watermarks = $this->watermarkService->getByUser();

        return ApiResponse::success(WatermarkResource::collection($watermarks));
    }

    /**
     * Get single watermark
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $watermark = $this->watermarkService->getById($id);

        return ApiResponse::success(new WatermarkResource($watermark));
    }

    /**
     * Create a watermark
     */
    public function store(StoreWatermarkRequest $request): JsonResponse
    {
        $watermark = $this->watermarkService->create($request->validated());

        return ApiResponse::success(new WatermarkResource($watermark), 201);
    }

    /**
     * Update a watermark
     */
    public function update(UpdateWatermarkRequest $request, string $id): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $watermark = $this->watermarkService->update($id, $request->validated());

        return ApiResponse::success(new WatermarkResource($watermark));
    }

    /**
     * Delete a watermark
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $this->watermarkService->delete($id);

        return ApiResponse::success(null, 204);
    }

    /**
     * Upload watermark image
     */
    public function uploadImage(ImageUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');
        if (! $file) {
            return ApiResponse::error('No file provided', 'NO_FILE', 400);
        }

        $result = $this->watermarkService->uploadImage($file);

        return ApiResponse::success($result);
    }

    /**
     * Duplicate a watermark
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $duplicated = $this->watermarkService->duplicate($id);

        return ApiResponse::success(new WatermarkResource($duplicated), 201);
    }

    /**
     * Get watermark usage count
     */
    public function usage(Request $request, string $id): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $count = $this->watermarkService->getUsageCount($id);

        return ApiResponse::success(['count' => $count]);
    }
}
