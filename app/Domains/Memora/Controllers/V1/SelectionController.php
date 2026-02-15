<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\RecoverMediaRequest;
use App\Domains\Memora\Requests\V1\SetCoverPhotoRequest;
use App\Domains\Memora\Requests\V1\StoreSelectionRequest;
use App\Domains\Memora\Requests\V1\UpdateSelectionRequest;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\SelectionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelectionController extends Controller
{
    protected SelectionService $selectionService;

    public function __construct(SelectionService $selectionService)
    {
        $this->selectionService = $selectionService;
    }

    /**
     * Get all selections with optional search, sort, filter, and pagination parameters
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

        $result = $this->selectionService->getAll(
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
     * Get a selection by ID
     */
    public function show(string $id): JsonResponse
    {
        $selection = $this->selectionService->find($id);

        return ApiResponse::success($selection);
    }

    /**
     * Get storage used by this selection (lightweight, for badge refresh after upload).
     */
    public function storage(string $id): JsonResponse
    {
        $bytes = $this->selectionService->getStorageUsed($id);

        return ApiResponse::success(['storageUsedBytes' => $bytes]);
    }

    /**
     * Create a selection (standalone if project_uuid is null, project-based if provided)
     */
    public function store(StoreSelectionRequest $request): JsonResponse
    {
        $selection = $this->selectionService->create($request->validated());

        $this->logSelectionActivity('created', 'Selection phase created', ['selection_uuid' => $selection->uuid ?? $selection['uuid'] ?? null], $request);

        return ApiResponse::success($selection, 201);
    }

    /**
     * Update a selection
     */
    public function update(UpdateSelectionRequest $request, string $id): JsonResponse
    {
        $selection = $this->selectionService->update($id, $request->validated());

        $this->logSelectionActivity('updated', 'Selection phase updated', ['selection_uuid' => $id], $request);

        return ApiResponse::success($selection);
    }

    /**
     * Delete a selection
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->selectionService->delete($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'deleted',
                null,
                'Selection phase deleted',
                ['selection_uuid' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log selection activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(null, 204);
    }

    /**
     * Publish a selection (creative can only publish to active, not complete)
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $selection = $this->selectionService->publish($id);

        $this->logSelectionActivity('selection_published', 'Selection published', ['selection_uuid' => $id], $request);

        return ApiResponse::success($selection);
    }

    /**
     * Recover deleted media
     */
    public function recover(RecoverMediaRequest $request, string $id): JsonResponse
    {
        $result = $this->selectionService->recover($id, $request->validated()['mediaIds']);

        $this->logSelectionActivity('selection_media_recovered', 'Selection media recovered', ['selection_uuid' => $id, 'media_count' => count($request->validated()['mediaIds'])], $request);

        return ApiResponse::success($result);
    }

    /**
     * Get selected media
     */
    public function getSelectedMedia(Request $request, string $id): JsonResponse
    {
        $setUuid = $request->query('setId');
        $media = $this->selectionService->getSelectedMedia($id, $setUuid);

        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Get selected filenames
     */
    public function getSelectedFilenames(Request $request, string $id): JsonResponse
    {
        $setId = $request->query('setId');
        $result = $this->selectionService->getSelectedFilenames($id, $setId);

        return ApiResponse::success($result);
    }

    /**
     * Reset selection limit
     */
    public function resetSelectionLimit(Request $request, string $id): JsonResponse
    {
        try {
            $selection = $this->selectionService->resetSelectionLimit($id);

            $this->logSelectionActivity('selection_limit_reset', 'Selection limit reset', ['selection_uuid' => $id], $request);

            return ApiResponse::success($selection);
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
            $selection = $this->selectionService->setCoverPhotoFromMedia($id, $validated['media_uuid'], $focalPoint);

            $this->logSelectionActivity('selection_cover_photo_set', 'Selection cover photo set', ['selection_uuid' => $id], $request);

            return ApiResponse::success($selection);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Selection or media not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'INVALID_MEDIA', 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to set cover photo', [
                'selection_id' => $id,
                'media_uuid' => $request->input('media_uuid'),
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to set cover photo', 'SET_COVER_FAILED', 500);
        }
    }

    /**
     * Toggle star status for a selection
     */
    public function toggleStar(Request $request, string $id): JsonResponse
    {
        $result = $this->selectionService->toggleStar($id);

        $action = ($result['starred'] ?? false) ? 'selection_starred' : 'selection_unstarred';
        $this->logSelectionActivity($action, $result['starred'] ? 'Selection starred' : 'Selection unstarred', ['selection_uuid' => $id], $request);

        return ApiResponse::success($result);
    }

    /**
     * Duplicate a selection
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $duplicated = $this->selectionService->duplicate($id);

        $this->logSelectionActivity('selection_duplicated', 'Selection duplicated', ['selection_uuid' => $id, 'new_uuid' => $duplicated->uuid ?? null], $request);

        return ApiResponse::success($duplicated, 201);
    }

    private function logSelectionActivity(string $action, string $description, array $properties, Request $request): void
    {
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                $action,
                null,
                $description,
                $properties,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log selection activity', ['error' => $e->getMessage()]);
        }
    }
}
