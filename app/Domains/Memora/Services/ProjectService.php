<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Services\Pagination\PaginationService;

class ProjectService
{
    protected PaginationService $paginationService;

    public function __construct(PaginationService $paginationService)
    {
        $this->paginationService = $paginationService;
    }

    /**
     * List projects with filters and pagination
     *
     * @param array $filters
     * @param int|null $page
     * @param int|null $perPage
     * @return array Paginated response with data and pagination metadata
     */
    public function list(array $filters = [], ?int $page = null, ?int $perPage = null)
    {
        $query = MemoraProject::query()->with(['mediaSets']);

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Filter by parent
        if (isset($filters['parentId'])) {
            $query->where('parent_id', $filters['parentId']);
        }

        // Search
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Default ordering
        $query->orderBy('created_at', 'desc');

        // Paginate the query
        $perPage = $perPage ?? 10;
        $paginator = $this->paginationService->paginate($query, $perPage, $page);

        // Transform items to resources
        $data = \App\Domains\Memora\Resources\V1\ProjectResource::collection($paginator->items());

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
     * Get a single project
     *
     * @param string $id
     * @return MemoraProject
     */
    public function find(string $id): MemoraProject
    {
        return MemoraProject::with(['mediaSets', 'selections', 'proofing', 'collections'])->findOrFail($id);
    }

    /**
     * Create a new project
     *
     * @param array $data
     * @param int $userId
     * @return MemoraProject
     */
    public function create(array $data, int $userId): MemoraProject
    {
        $project = MemoraProject::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'has_selections' => $data['hasSelections'] ?? false,
            'has_proofing' => $data['hasProofing'] ?? false,
            'has_collections' => $data['hasCollections'] ?? false,
            'parent_id' => $data['parentId'] ?? null,
            'preset_id' => $data['presetId'] ?? null,
            'watermark_id' => $data['watermarkId'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);

        // Create media sets if provided
        if (isset($data['mediaSets']) && is_array($data['mediaSets'])) {
            foreach ($data['mediaSets'] as $setData) {
                MemoraMediaSet::create([
                    'project_id' => $project->id,
                    'name' => $setData['name'],
                    'description' => $setData['description'] ?? null,
                    'order' => $setData['order'] ?? 0,
                ]);
            }
        }

        return $project->load('mediaSets');
    }

    /**
     * Update a project
     *
     * @param string $id
     * @param array $data
     * @return MemoraProject
     */
    public function update(string $id, array $data): MemoraProject
    {
        $project = MemoraProject::findOrFail($id);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['settings'])) $updateData['settings'] = $data['settings'];

        $project->update($updateData);

        // Update media sets if provided
        if (isset($data['mediaSets']) && is_array($data['mediaSets'])) {
            // Handle media sets update logic
            // This is a simplified version - full implementation would handle adds/updates/deletes
        }

        return $project->load('mediaSets');
    }

    /**
     * Delete a project
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        $project = MemoraProject::findOrFail($id);
        return $project->delete();
    }

    /**
     * Get project phases
     *
     * @param string $id
     * @return array
     */
    public function getPhases(string $id): array
    {
        $project = MemoraProject::findOrFail($id);

        $selection = $project->selections()->first();
        $proofing = $project->proofing()->first();
        $collection = $project->collections()->first();

        return [
            'selection' => $selection ? [
                'id' => $selection->id,
                'projectId' => $selection->project_id,
                'name' => $selection->name,
                'status' => $selection->status,
                'selectionCompletedAt' => $selection->selection_completed_at?->toIso8601String(),
                'autoDeleteDate' => $selection->auto_delete_date?->toIso8601String(),
                'createdAt' => $selection->created_at->toIso8601String(),
                'updatedAt' => $selection->updated_at->toIso8601String(),
            ] : null,
            'proofing' => $proofing ? [
                'id' => $proofing->id,
                'projectId' => $proofing->project_id,
                'name' => $proofing->name,
                'status' => $proofing->status,
                'maxRevisions' => $proofing->max_revisions,
                'currentRevision' => $proofing->current_revision,
                'createdAt' => $proofing->created_at->toIso8601String(),
                'updatedAt' => $proofing->updated_at->toIso8601String(),
            ] : null,
            'collection' => $collection ? [
                'id' => $collection->id,
                'projectId' => $collection->project_id,
                'name' => $collection->name,
                'status' => $collection->status,
                'createdAt' => $collection->created_at->toIso8601String(),
                'updatedAt' => $collection->updated_at->toIso8601String(),
            ] : null,
        ];
    }
}
