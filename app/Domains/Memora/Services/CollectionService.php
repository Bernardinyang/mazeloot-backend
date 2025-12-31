<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraProject;
use App\Services\Pagination\PaginationService;
use Illuminate\Support\Facades\Auth;

class CollectionService
{
    protected PaginationService $paginationService;

    public function __construct(PaginationService $paginationService)
    {
        $this->paginationService = $paginationService;
    }

    /**
     * List collections (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, lists collections for that project. If null, lists all user collections.
     * @return array Paginated response with data and pagination metadata
     */
    public function list(?string $projectId, ?int $page = null, ?int $perPage = null)
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraCollection::where('user_uuid', $user->uuid);

        if ($projectId) {
            // Validate project exists and belongs to user
            $project = MemoraProject::where('uuid', $projectId)
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();
            $query->where('project_uuid', $projectId);
        } else {
            // Standalone collections only
            $query->whereNull('project_uuid');
        }

        $query->orderBy('created_at', 'desc');

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
     * Create a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, creates collection for that project. If null, creates standalone collection.
     */
    public function create(?string $projectId, array $data): MemoraCollection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $project = null;
        if ($projectId) {
            // Validate project exists and belongs to user
            $project = MemoraProject::where('uuid', $projectId)
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();
        }

        return MemoraCollection::create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $projectId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'color' => $data['color'] ?? $project?->color ?? '#8B5CF6',
        ]);
    }

    /**
     * Update a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, validates collection belongs to that project. If null, finds any collection by ID.
     */
    public function update(?string $projectId, string $id, array $data): MemoraCollection
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
     * Get a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, validates collection belongs to that project. If null, finds any collection by ID.
     */
    public function find(?string $projectId, string $id): MemoraCollection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraCollection::where('user_uuid', $user->uuid)
            ->where('uuid', $id);

        if ($projectId) {
            // Validate project exists and collection belongs to it
            MemoraProject::where('uuid', $projectId)
                ->where('user_uuid', $user->uuid)
                ->firstOrFail();
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find collection regardless of project association

        return $query->firstOrFail();
    }

    /**
     * Delete a collection (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, validates collection belongs to that project. If null, finds any collection by ID.
     */
    public function delete(?string $projectId, string $id): bool
    {
        $collection = $this->find($projectId, $id);

        return $collection->delete();
    }
}
