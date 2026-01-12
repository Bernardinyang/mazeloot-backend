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

        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set
     */
    public function update(UpdateMediaSetRequest $request, string $selectionId, string $id): JsonResponse
    {
        $set = $this->mediaSetService->update($selectionId, $id, $request->validated());

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set
     */
    public function destroy(string $selectionId, string $id): JsonResponse
    {
        $this->mediaSetService->delete($selectionId, $id);

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
        $proofingId = $request->route('proofingId') ?? $proofingId;
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
        $proofingId = $request->route('proofingId') ?? $proofingId;
        $id = $request->route('id') ?? $id;
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
        $proofingId = $request->route('proofingId') ?? $proofingId;
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->createForProofing($proofingId, $request->validated(), $projectId);

        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set for proofing
     * Unified route: /proofing/{proofingId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function updateForProofing(UpdateMediaSetRequest $request, string $proofingId, string $id): JsonResponse
    {
        $proofingId = $request->route('proofingId') ?? $proofingId;
        $id = $request->route('id') ?? $id;
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->updateForProofing($proofingId, $id, $request->validated(), $projectId);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set for proofing
     * Unified route: /proofing/{proofingId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function destroyForProofing(Request $request, string $proofingId, string $id): JsonResponse
    {
        $proofingId = $request->route('proofingId') ?? $proofingId;
        $id = $request->route('id') ?? $id;
        $projectId = $request->query('projectId');
        $this->mediaSetService->deleteForProofing($proofingId, $id, $projectId);

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets for proofing
     * Unified route: /proofing/{proofingId}/sets/reorder
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function reorderForProofing(Request $request, string $proofingId): JsonResponse
    {
        $proofingId = $request->route('proofingId') ?? $proofingId;
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $projectId = $request->query('projectId');
        $this->mediaSetService->reorderForProofing($proofingId, $request->input('setIds'), $projectId);

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
        $collectionId = $request->route('collectionId') ?? $collectionId;
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
        $collectionId = $request->route('collectionId') ?? $collectionId;
        $id = $request->route('id') ?? $id;
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
        $collectionId = $request->route('collectionId') ?? $collectionId;
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
        $collectionId = $request->route('collectionId') ?? $collectionId;
        $id = $request->route('id') ?? $id;
        $projectId = $request->query('projectId');
        $set = $this->mediaSetService->updateForCollection($collectionId, $id, $request->validated(), $projectId);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set for collection
     * Unified route: /collections/{collectionId}/sets/{id}
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function destroyForCollection(Request $request, string $collectionId, string $id): JsonResponse
    {
        $collectionId = $request->route('collectionId') ?? $collectionId;
        $id = $request->route('id') ?? $id;
        $projectId = $request->query('projectId');
        $this->mediaSetService->deleteForCollection($collectionId, $id, $projectId);

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets for collection
     * Unified route: /collections/{collectionId}/sets/reorder
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function reorderForCollection(Request $request, string $collectionId): JsonResponse
    {
        $collectionId = $request->route('collectionId') ?? $collectionId;
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $projectId = $request->query('projectId');
        $this->mediaSetService->reorderForCollection($collectionId, $request->input('setIds'), $projectId);

        return ApiResponse::success(['message' => 'Sets reordered successfully']);
    }

    // ==================== Raw Files Media Sets ====================

    /**
     * Get all media sets for a raw files phase with optional pagination parameters
     */
    public function indexForRawFiles(Request $request, string $rawFilesId): JsonResponse
    {
        $rawFilesId = $request->route('rawFilesId') ?? $rawFilesId;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $result = $this->mediaSetService->getByRawFiles($rawFilesId, $page, $perPage);

        return ApiResponse::success($result);
    }

    /**
     * Get a single media set for raw files
     */
    public function showForRawFiles(Request $request, string $rawFilesId, string $id): JsonResponse
    {
        $rawFilesId = $request->route('rawFilesId') ?? $rawFilesId;
        $id = $request->route('id') ?? $id;
        $set = $this->mediaSetService->findByRawFiles($rawFilesId, $id);

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Create a media set for raw files
     */
    public function storeForRawFiles(StoreMediaSetRequest $request, string $rawFilesId): JsonResponse
    {
        $rawFilesId = $request->route('rawFilesId') ?? $rawFilesId;
        $set = $this->mediaSetService->createForRawFiles($rawFilesId, $request->validated());

        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set for raw files
     */
    public function updateForRawFiles(UpdateMediaSetRequest $request, string $rawFilesId, string $id): JsonResponse
    {
        $rawFilesId = $request->route('rawFilesId') ?? $rawFilesId;
        $id = $request->route('id') ?? $id;
        $set = $this->mediaSetService->updateForRawFiles($rawFilesId, $id, $request->validated());

        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set for raw files
     */
    public function destroyForRawFiles(Request $request, string $rawFilesId, string $id): JsonResponse
    {
        $rawFilesId = $request->route('rawFilesId') ?? $rawFilesId;
        $id = $request->route('id') ?? $id;
        $this->mediaSetService->deleteForRawFiles($rawFilesId, $id);

        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets for raw files
     */
    public function reorderForRawFiles(Request $request, string $rawFilesId): JsonResponse
    {
        $rawFilesId = $request->route('rawFilesId') ?? $rawFilesId;
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $this->mediaSetService->reorderForRawFiles($rawFilesId, $request->input('setIds'));

        return ApiResponse::success(['message' => 'Sets reordered successfully']);
    }
}
