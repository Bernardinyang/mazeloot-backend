<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\StoreCollectionRequest;
use App\Domains\Memora\Requests\V1\UpdateCollectionRequest;
use App\Domains\Memora\Resources\V1\CollectionResource;
use App\Domains\Memora\Services\CollectionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    protected CollectionService $collectionService;

    public function __construct(CollectionService $collectionService)
    {
        $this->collectionService = $collectionService;
    }

    public function index(Request $request, string $projectId): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10))); // Limit between 1 and 100

        $result = $this->collectionService->list($projectId, $page, $perPage);

        return ApiResponse::success($result);
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
