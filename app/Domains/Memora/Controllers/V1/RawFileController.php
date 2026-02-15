<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\RecoverMediaRequest;
use App\Domains\Memora\Requests\V1\SetCoverPhotoRequest;
use App\Domains\Memora\Requests\V1\StoreRawFileRequest;
use App\Domains\Memora\Requests\V1\UpdateRawFileRequest;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\RawFileService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RawFileController extends Controller
{
    protected RawFileService $rawFileService;

    public function __construct(RawFileService $rawFileService)
    {
        $this->rawFileService = $rawFileService;
    }

    /**
     * Get all raw files with optional search, sort, filter, and pagination parameters
     */
    public function index(Request $request): JsonResponse
    {
        $projectUuid = $request->query('project_uuid');
        $search = $request->query('search');
        $sortBy = $request->query('sort_by');
        $status = $request->query('status');
        $starred = $request->has('starred') ? filter_var($request->query('starred'), FILTER_VALIDATE_BOOLEAN) : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10))); // Limit between 1 and 100

        $result = $this->rawFileService->getAll(
            projectUuid: $projectUuid,
            search: $search,
            sortBy: $sortBy,
            status: $status,
            starred: $starred,
            page: $page,
            perPage: $perPage
        );

        return ApiResponse::success($result);
    }

    /**
     * Get a raw file by ID
     */
    public function show(string $id): JsonResponse
    {
        $rawFile = $this->rawFileService->find($id);

        return ApiResponse::success($rawFile);
    }

    /**
     * Get storage used by this raw file (lightweight, for badge refresh after upload).
     */
    public function storage(string $id): JsonResponse
    {
        $bytes = $this->rawFileService->getStorageUsed($id);

        return ApiResponse::success(['storageUsedBytes' => $bytes]);
    }

    /**
     * Create a raw file (standalone if project_uuid is null, project-based if provided)
     */
    public function store(StoreRawFileRequest $request): JsonResponse
    {
        $rawFile = $this->rawFileService->create($request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'created',
                $rawFile,
                'Raw file created',
                ['raw_file_uuid' => $rawFile->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($rawFile, 201);
    }

    /**
     * Update a raw file
     */
    public function update(UpdateRawFileRequest $request, string $id): JsonResponse
    {
        $rawFile = $this->rawFileService->update($id, $request->validated());

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'updated',
                $rawFile,
                'Raw file updated',
                ['raw_file_uuid' => $rawFile->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($rawFile);
    }

    /**
     * Delete a raw file
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->rawFileService->delete($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'deleted',
                null,
                'Raw file deleted',
                ['raw_file_uuid' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(null, 204);
    }

    /**
     * Publish a raw file (creative can only publish to active, not complete)
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $rawFile = $this->rawFileService->publish($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'raw_file_published',
                $rawFile,
                'Raw file published',
                ['raw_file_uuid' => $rawFile->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($rawFile);
    }

    /**
     * Recover deleted media
     */
    public function recover(RecoverMediaRequest $request, string $id): JsonResponse
    {
        $result = $this->rawFileService->recover($id, $request->validated()['mediaIds']);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'raw_file_media_recovered',
                null,
                'Raw file media recovered',
                ['raw_file_uuid' => $id, 'media_count' => count($request->validated()['mediaIds'])],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($result);
    }

    /**
     * Get selected media
     */
    public function getSelectedMedia(Request $request, string $id): JsonResponse
    {
        $setUuid = $request->query('setId');
        $media = $this->rawFileService->getSelectedMedia($id, $setUuid);

        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Get selected filenames
     */
    public function getSelectedFilenames(Request $request, string $id): JsonResponse
    {
        $setId = $request->query('setId');
        $result = $this->rawFileService->getSelectedFilenames($id, $setId);

        return ApiResponse::success($result);
    }

    /**
     * Reset raw file limit
     */
    public function resetRawFileLimit(Request $request, string $id): JsonResponse
    {
        try {
            $rawFile = $this->rawFileService->resetRawFileLimit($id);

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'raw_file_limit_reset',
                    $rawFile,
                    'Raw file selection limit reset',
                    ['raw_file_uuid' => $id],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success($rawFile);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'INVALID_OPERATION', 400);
        }
    }

    /**
     * Set cover photo from media thumbnail URL
     */
    public function setCoverPhoto(SetCoverPhotoRequest $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            $focalPoint = $validated['focal_point'] ?? null;
            $rawFile = $this->rawFileService->setCoverPhotoFromMedia($id, $validated['media_uuid'], $focalPoint);

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'raw_file_cover_photo_set',
                    $rawFile,
                    'Raw file cover photo set',
                    ['raw_file_uuid' => $id],
                    $request->user(),
                    $request
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
            }

            return ApiResponse::success($rawFile);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw file or media not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'INVALID_MEDIA', 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to set cover photo', [
                'raw_file_id' => $id,
                'media_uuid' => $request->input('media_uuid'),
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to set cover photo', 'SET_COVER_FAILED', 500);
        }
    }

    /**
     * Toggle star status for a raw file
     */
    public function toggleStar(Request $request, string $id): JsonResponse
    {
        $result = $this->rawFileService->toggleStar($id);

        try {
            $rawFile = $this->rawFileService->find($id);
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                $result['starred'] ? 'raw_file_starred' : 'raw_file_unstarred',
                $rawFile,
                $result['starred'] ? 'Raw file starred' : 'Raw file unstarred',
                ['raw_file_uuid' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($result);
    }

    /**
     * Duplicate a raw file
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $duplicated = $this->rawFileService->duplicate($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'raw_file_duplicated',
                $duplicated,
                'Raw file duplicated',
                ['source_uuid' => $id, 'new_uuid' => $duplicated->uuid],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log raw file activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($duplicated, 201);
    }
}
