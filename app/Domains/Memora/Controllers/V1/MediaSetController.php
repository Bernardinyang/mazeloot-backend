<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreMediaSetRequest;
use App\Domains\Memora\Requests\V1\UpdateMediaSetRequest;
use App\Domains\Memora\Resources\V1\MediaSetResource;
use App\Domains\Memora\Services\MediaSetService;
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
     * Get all media sets for a selection
     */
    public function index(string $selectionId): JsonResponse
    {
        $sets = $this->mediaSetService->getBySelection($selectionId);
        return ApiResponse::success(MediaSetResource::collection($sets));
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
}

