<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreCollectionRequest;
use App\Domains\Memora\Requests\V1\UpdateCollectionRequest;
use App\Domains\Memora\Resources\V1\CollectionResource;
use App\Domains\Memora\Services\CollectionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CollectionController extends Controller
{
    protected CollectionService $collectionService;

    public function __construct(CollectionService $collectionService)
    {
        $this->collectionService = $collectionService;
    }

    public function index(string $projectId): JsonResponse
    {
        $collections = $this->collectionService->list($projectId);
        return ApiResponse::success(CollectionResource::collection($collections));
    }

    public function show(string $projectId, string $id): JsonResponse
    {
        $collection = $this->collectionService->find($projectId, $id);
        return ApiResponse::success(new CollectionResource($collection));
    }

    public function store(StoreCollectionRequest $request, string $projectId): JsonResponse
    {
        $collection = $this->collectionService->create($projectId, $request->validated());
        return ApiResponse::success(new CollectionResource($collection), 201);
    }

    public function update(UpdateCollectionRequest $request, string $projectId, string $id): JsonResponse
    {
        $collection = $this->collectionService->update($projectId, $id, $request->validated());
        return ApiResponse::success(new CollectionResource($collection));
    }

    public function destroy(string $projectId, string $id): JsonResponse
    {
        $this->collectionService->delete($projectId, $id);
        return ApiResponse::success(null, 204);
    }
}

