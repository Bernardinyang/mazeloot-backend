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

    /**
     * List collections (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function index(Request $request): JsonResponse
    {
        $projectId = $request->query('projectId');
        $starred = $request->has('starred') ? filter_var($request->query('starred'), FILTER_VALIDATE_BOOLEAN) : null;
        $search = $request->query('search');
        $sortBy = $request->query('sort_by');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $result = $this->collectionService->list($projectId, $starred, $search, $sortBy, $page, $perPage);

        return ApiResponse::success($result);
    }

    /**
     * Show collection (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $collection = $this->collectionService->find($projectId, $id);

        return ApiResponse::success(new CollectionResource($collection));
    }

    /**
     * Create collection (unified for standalone and project-based)
     * For project-based: pass projectId in request body or ?projectId=xxx as query parameter
     */
    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $projectId = $request->input('projectId') ?? $request->query('projectId');
        $collection = $this->collectionService->create($projectId, $request->validated());

        $this->logCollectionActivity('created', 'Collection created', ['collection_uuid' => $collection->uuid], $request);

        return ApiResponse::success(new CollectionResource($collection), 201);
    }

    /**
     * Update collection (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function update(UpdateCollectionRequest $request, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $collection = $this->collectionService->update($projectId, $id, $request->validated());

        $this->logCollectionActivity('updated', 'Collection updated', ['collection_uuid' => $id], $request);

        return ApiResponse::success(new CollectionResource($collection));
    }

    /**
     * Delete collection (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $this->collectionService->delete($projectId, $id);

        $this->logCollectionActivity('deleted', 'Collection deleted', ['collection_uuid' => $id], $request);

        return ApiResponse::success(null, 204);
    }

    /**
     * Toggle star status for a collection (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function toggleStar(Request $request, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $result = $this->collectionService->toggleStar($projectId, $id);

        $action = ($result['starred'] ?? false) ? 'collection_starred' : 'collection_unstarred';
        $this->logCollectionActivity($action, $result['starred'] ? 'Collection starred' : 'Collection unstarred', ['collection_uuid' => $id], $request);

        return ApiResponse::success($result);
    }

    /**
     * Duplicate collection (unified for standalone and project-based)
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $projectId = $request->query('projectId');
        $duplicated = $this->collectionService->duplicate($projectId, $id);

        $this->logCollectionActivity('collection_duplicated', 'Collection duplicated', ['collection_uuid' => $id, 'new_uuid' => $duplicated->uuid], $request);

        return ApiResponse::success(new CollectionResource($duplicated), 201);
    }

    private function logCollectionActivity(string $action, string $description, array $properties, Request $request): void
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
            \Illuminate\Support\Facades\Log::warning('Failed to log collection activity', ['error' => $e->getMessage()]);
        }
    }
}
