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
     */
    public function indexForProofing(Request $request, string $proofingId): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $result = $this->mediaSetService->getByProofing($proofingId, $page, $perPage);
        return ApiResponse::success($result);
    }

    /**
     * Get a single media set for proofing
     */
    public function showForProofing(string $proofingId, string $id): JsonResponse
    {
        $set = $this->mediaSetService->findByProofing($proofingId, $id);
        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Create a media set for proofing
     */
    public function storeForProofing(StoreMediaSetRequest $request, string $proofingId): JsonResponse
    {
        $set = $this->mediaSetService->createForProofing($proofingId, $request->validated());
        return ApiResponse::success(new MediaSetResource($set), 201);
    }

    /**
     * Update a media set for proofing
     */
    public function updateForProofing(UpdateMediaSetRequest $request, string $proofingId, string $id): JsonResponse
    {
        $set = $this->mediaSetService->updateForProofing($proofingId, $id, $request->validated());
        return ApiResponse::success(new MediaSetResource($set));
    }

    /**
     * Delete a media set for proofing
     */
    public function destroyForProofing(string $proofingId, string $id): JsonResponse
    {
        $this->mediaSetService->deleteForProofing($proofingId, $id);
        return ApiResponse::success(null, 204);
    }

    /**
     * Reorder media sets for proofing
     */
    public function reorderForProofing(Request $request, string $proofingId): JsonResponse
    {
        $request->validate([
            'setIds' => 'required|array',
            'setIds.*' => 'uuid',
        ]);

        $this->mediaSetService->reorderForProofing($proofingId, $request->input('setIds'));
        return ApiResponse::success(['message' => 'Sets reordered successfully']);
    }
}

