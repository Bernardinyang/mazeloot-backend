<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraProject;
use App\Services\Pagination\PaginationService;

class CollectionService
{
    protected PaginationService $paginationService;

    public function __construct(PaginationService $paginationService)
    {
        $this->paginationService = $paginationService;
    }

    /**
     * List collections for a project with pagination
     *
     * @return array Paginated response with data and pagination metadata
     */
    public function list(string $projectId, ?int $page = null, ?int $perPage = null)
    {
        // Validate project exists
        MemoraProject::findOrFail($projectId);

        $query = MemoraCollection::where('project_uuid', $projectId)
            ->orderBy('created_at', 'desc');

        // Paginate the query
        $perPage = $perPage ?? 10;
        $paginator = $this->paginationService->paginate($query, $perPage, $page);

        // Transform items to resources
        $data = \App\Domains\Memora\Resources\V1\CollectionResource::collection($paginator->items());

        // Format response with pagination metadata
        return [
            'data' => $data,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Create a collection
     */
    public function create(string $projectId, array $data): MemoraCollection
    {
        // Validate project exists
        $project = MemoraProject::findOrFail($projectId);

        return MemoraCollection::create([
            'user_uuid' => $project->user_uuid,
            'project_uuid' => $projectId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'color' => $data['color'] ?? $project->color ?? '#8B5CF6',
        ]);
    }

    /**
     * Update a collection
     */
    public function update(string $projectId, string $id, array $data): MemoraCollection
    {
        $collection = $this->find($projectId, $id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $collection->update($updateData);

        return $collection->fresh();
    }

    /**
     * Get a collection
     */
    public function find(string $projectId, string $id): MemoraCollection
    {
        return MemoraCollection::where('project_uuid', $projectId)->findOrFail($id);
    }

    /**
     * Delete a collection
     */
    public function delete(string $projectId, string $id): bool
    {
        $collection = $this->find($projectId, $id);

        return $collection->delete();
    }
}
