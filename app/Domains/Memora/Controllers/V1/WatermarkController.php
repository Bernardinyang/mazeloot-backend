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
    public function show(string $id): JsonResponse
    {
        $watermark = $this->watermarkService->getById($id);

        return ApiResponse::success(new WatermarkResource($watermark));
    }

    /**
     * Create a watermark
     */
    public function store(StoreWatermarkRequest $request): JsonResponse
    {
        $watermark = $this->watermarkService->create($request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'created',
                $watermark,
                'Watermark created',
                ['watermark_uuid' => $watermark->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log watermark activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new WatermarkResource($watermark), 201);
    }

    /**
     * Update a watermark
     */
    public function update(UpdateWatermarkRequest $request, string $id): JsonResponse
    {
        $watermark = $this->watermarkService->update($id, $request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $watermark,
                'Watermark updated',
                ['watermark_uuid' => $watermark->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log watermark activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(new WatermarkResource($watermark));
    }

    /**
     * Delete a watermark
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $watermark = $this->watermarkService->getById($id);
        $this->watermarkService->delete($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'deleted',
                null,
                'Watermark deleted',
                ['watermark_uuid' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log watermark activity', ['error' => $e->getMessage()]);
        }

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

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'watermark_image_uploaded',
                null,
                'Watermark image uploaded',
                ['watermark_uuid' => $result['uuid'] ?? null],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log watermark activity', ['error' => $e->getMessage()]);
        }
        return ApiResponse::success($result);
    }

    /**
     * Duplicate a watermark
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $duplicated = $this->watermarkService->duplicate($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'watermark_duplicated',
                $duplicated,
                'Watermark duplicated',
                ['watermark_uuid' => $id, 'new_uuid' => $duplicated->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log watermark activity', ['error' => $e->getMessage()]);
        }
        return ApiResponse::success(new WatermarkResource($duplicated), 201);
    }

    /**
     * Get watermark usage count
     */
    public function usage(string $id): JsonResponse
    {
        $count = $this->watermarkService->getUsageCount($id);

        return ApiResponse::success(['count' => $count]);
    }
}
