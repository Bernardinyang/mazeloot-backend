<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreMediaSetRequest;
use App\Domains\Memora\Requests\V1\UpdateMediaSetRequest;
use App\Domains\Memora\Resources\V1\MediaSetResource;
use App\Domains\Memora\Services\MediaSetService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaSetController extends Controller
{
    protected MediaSetService $mediaSetService;

    public function __construct(MediaSetService $mediaSetService)
    {
        $this->mediaSetService = $mediaSetService;
    }

    /**
     * Get all media sets for a selection with optional pagination parameters
     */
    public function index(Request $request, string $selectionId): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10))); // Limit between 1 and 100

        $result = $this->mediaSetService->getBySelection($selectionId, $page, $perPage);

        return ApiResponse::success($result);
    }

    /**
     * Get a single media set
     */
    public function show(string $selectionId, string $id): JsonResponse
    {
        $set = $this->mediaSetService->find($selectionId, $id);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Create a media set
     */
    public function store(StoreMediaSetRequest $request, string $selectionId): JsonResponse
    {
        $set = $this->mediaSetService->create($selectionId, $request->validated());

        $this->logMediaSetActivity('created', $set, 'Media set created (selection)', ['selection_id' => $selectionId], $request);

        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set
     */
    public function update(UpdateMediaSetRequest $request, string $selectionId, string $id): JsonResponse
    {
        $set = $this->mediaSetService->update($selectionId, $id, $request->validated());

        $this->logMediaSetActivity('updated', $set, 'Media set updated (selection)', ['selection_id' => $selectionId], $request);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set
     */
    public function destroy(Request $request, string $selectionId, string $id): JsonResponse
    {
        $this->mediaSetService->delete($selectionId, $id);

        $this->logMediaSetActivity('deleted', null, 'Media set deleted (selection)', ['selection_id' => $selectionId, 'set_id' => $id], $request);

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets
     */
    public function reorder(Request $request, string $selectionId): JsonResponse
    {
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $this->mediaSetService->reorder($selectionId, $request->input('setIds'));

        $this->logMediaSetActivity('media_sets_reordered', null, 'Media sets reordered (selection)', ['selection_id' => $selectionId, 'count' => count($request->input('setIds'))], $request);

        return ApiResponse::success(['message' => 'Sets reordered successfully']);
    }

    // ==================== Proofing Media Sets ====================

    /**
     * Get all media sets for a proofing with optional pagination parameters
     * Unified route: /proofing/{proofingId}/sets
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function indexForProofing(Request $request, string $proofingId): JsonResponse
    {
        $projectId = $request->query('projectId');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $result = $this->mediaSetService->getByProofing($proofingId, $page, $perPage, $projectId);

        return ApiResponse::success($result);
    }

    /**
     * Get a single media set for proofing
     * Unified route: /proofing/{proofingId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function showForProofing(Request $request, string $proofingId, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->findByProofing($proofingId, $id, $projectId);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Create a media set for proofing
     * Unified route: /proofing/{proofingId}/sets
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function storeForProofing(StoreMediaSetRequest $request, string $proofingId): JsonResponse
    {
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->createForProofing($proofingId, $request->validated(), $projectId);

        $this->logMediaSetActivity('created', $set, 'Media set created (proofing)', ['proofing_id' => $proofingId], $request);

        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set for proofing
     * Unified route: /proofing/{proofingId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function updateForProofing(UpdateMediaSetRequest $request, string $proofingId, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->updateForProofing($proofingId, $id, $request->validated(), $projectId);

        $this->logMediaSetActivity('updated', $set, 'Media set updated (proofing)', ['proofing_id' => $proofingId], $request);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set for proofing
     * Unified route: /proofing/{proofingId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function destroyForProofing(Request $request, string $proofingId, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $this->mediaSetService->deleteForProofing($proofingId, $id, $projectId);

        $this->logMediaSetActivity('deleted', null, 'Media set deleted (proofing)', ['proofing_id' => $proofingId, 'set_id' => $id], $request);

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets for proofing
     * Unified route: /proofing/{proofingId}/sets/reorder
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function reorderForProofing(Request $request, string $proofingId): JsonResponse
    {
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $projectId = $request->query('projectId');
        $this->mediaSetService->reorderForProofing($proofingId, $request->input('setIds'), $projectId);

        $this->logMediaSetActivity('media_sets_reordered', null, 'Media sets reordered (proofing)', ['proofing_id' => $proofingId, 'count' => count($request->input('setIds'))], $request);

        return ApiResponse::success(['message' => 'Sets reordered successfully']);
    }

    // ==================== Collection Media Sets ====================

    /**
     * Get all media sets for a collection with optional pagination parameters
     * Unified route: /collections/{collectionId}/sets
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function indexForCollection(Request $request, string $collectionId): JsonResponse
    {
        $projectId = $request->query('projectId');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $result = $this->mediaSetService->getByCollection($collectionId, $page, $perPage, $projectId);

        return ApiResponse::success($result);
    }

    /**
     * Get a single media set for collection
     * Unified route: /collections/{collectionId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function showForCollection(Request $request, string $collectionId, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->findByCollection($collectionId, $id, $projectId);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Create a media set for collection
     * Unified route: /collections/{collectionId}/sets
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function storeForCollection(StoreMediaSetRequest $request, string $collectionId): JsonResponse
    {
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->createForCollection($collectionId, $request->validated(), $projectId);

        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set for collection
     * Unified route: /collections/{collectionId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function updateForCollection(UpdateMediaSetRequest $request, string $collectionId, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->updateForCollection($collectionId, $id, $request->validated(), $projectId);

        $this->logMediaSetActivity('updated', $set, 'Media set updated (collection)', ['collection_id' => $collectionId], $request);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set for collection
     * Unified route: /collections/{collectionId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function destroyForCollection(Request $request, string $collectionId, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $this->mediaSetService->deleteForCollection($collectionId, $id, $projectId);

        $this->logMediaSetActivity('deleted', null, 'Media set deleted (collection)', ['collection_id' => $collectionId, 'set_id' => $id], $request);

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets for collection
     * Unified route: /collections/{collectionId}/sets/reorder
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function reorderForCollection(Request $request, string $collectionId): JsonResponse
    {
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $projectId = $request->query('projectId');
        $this->mediaSetService->reorderForCollection($collectionId, $request->input('setIds'), $projectId);

        $this->logMediaSetActivity('media_sets_reordered', null, 'Media sets reordered (collection)', ['collection_id' => $collectionId, 'count' => count($request->input('setIds'))], $request);

        return ApiResponse::success(['message' => 'Sets reordered successfully']);
    }

    // ==================== Raw File Media Sets ====================

    /**
     * Get all media sets for a raw file with optional pagination parameters
     */
    public function indexForRawFile(Request $request, string $rawFileId): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $result = $this->mediaSetService->getByRawFile($rawFileId, $page, $perPage);

        return ApiResponse::success($result);
    }

    /**
     * Get a single media set for raw file
     */
    public function showForRawFile(string $rawFileId, string $id): JsonResponse
    {
        $set = $this->mediaSetService->findByRawFile($rawFileId, $id);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Create a media set for raw file
     */
    public function storeForRawFile(StoreMediaSetRequest $request, string $rawFileId): JsonResponse
    {
        $set = $this->mediaSetService->createForRawFile($rawFileId, $request->validated());

        $this->logMediaSetActivity('created', $set, 'Media set created (raw file)', ['raw_file_id' => $rawFileId], $request);

        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set for raw file
     */
    public function updateForRawFile(UpdateMediaSetRequest $request, string $rawFileId, string $id): JsonResponse
    {
        $set = $this->mediaSetService->updateForRawFile($rawFileId, $id, $request->validated());

        $this->logMediaSetActivity('updated', $set, 'Media set updated (raw file)', ['raw_file_id' => $rawFileId], $request);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set for raw file
     */
    public function destroyForRawFile(Request $request, string $rawFileId, string $id): JsonResponse
    {
        $this->mediaSetService->deleteForRawFile($rawFileId, $id);

        $this->logMediaSetActivity('deleted', null, 'Media set deleted (raw file)', ['raw_file_id' => $rawFileId, 'set_id' => $id], $request);

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets for raw file
     */
    public function reorderForRawFile(Request $request, string $rawFileId): JsonResponse
    {
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $this->mediaSetService->reorderForRawFile($rawFileId, $request->input('setIds'));

        $this->logMediaSetActivity('media_sets_reordered', null, 'Media sets reordered (raw file)', ['raw_file_id' => $rawFileId, 'count' => count($request->input('setIds'))], $request);

        return ApiResponse::success(['message' => 'Sets reordered successfully']);
    }

    private function logMediaSetActivity(string $action, $subject, string $description, array $properties, Request $request): void
    {
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                $action,
                $subject,
                $description,
                $properties,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log media set activity', ['error' => $e->getMessage()]);
        }
    }
}
