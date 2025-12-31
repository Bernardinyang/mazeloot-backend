<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraSelection;
use App\Services\Pagination\PaginationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MediaSetService
{
    protected PaginationService $paginationService;

    public function __construct(PaginationService $paginationService)
    {
        $this->paginationService = $paginationService;
    }
    /**
     * Create a media set in a selection
     */
    public function create(string $selectionId, array $data): MemoraMediaSet
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $selection = MemoraSelection::findOrFail($selectionId);

        // Verify user owns the selection
        if ($selection->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this selection');
        }

        // Get the maximum order for sets in this selection
        $maxOrder = MemoraMediaSet::where('selection_uuid', $selectionId)
            ->max('order') ?? -1;

        $setData = [
            'user_uuid' => Auth::user()->uuid,
            'selection_uuid' => $selectionId,
            'project_uuid' => $selection->project_uuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'order' => $maxOrder + 1,
        ];

        // Handle selection_limit (support both snake_case and camelCase)
        // Use array_key_exists to allow null values to be set
        if (array_key_exists('selection_limit', $data) || array_key_exists('selectionLimit', $data)) {
            $limit = $data['selection_limit'] ?? $data['selectionLimit'] ?? null;
            // Explicitly set to null if null, empty string, or 0; otherwise cast to int
            if ($limit === null || $limit === '' || $limit === 0) {
                $setData['selection_limit'] = null;
            } else {
                $setData['selection_limit'] = (int) $limit;
            }
        }

        return MemoraMediaSet::create($setData);
    }

    /**
     * Get all media sets for a selection with pagination
     *
     * @param string $selectionId
     * @param int|null $page
     * @param int|null $perPage
     * @return array Paginated response with data and pagination metadata
     */
    public function getBySelection(string $selectionId, ?int $page = null, ?int $perPage = null)
    {
        $query = MemoraMediaSet::where('selection_uuid', $selectionId)
            ->withCount('media')
            ->orderBy('order');

        // Paginate the query
        $perPage = $perPage ?? 10;
        $paginator = $this->paginationService->paginate($query, $perPage, $page);

        // Transform items to resources
        $data = \App\Domains\Memora\Resources\V1\MediaSetResource::collection($paginator->items());

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
     * Get all media sets for a proofing with pagination
     *
     * @param string $proofingId
     * @param int|null $page
     * @param int|null $perPage
     * @return array Paginated response with data and pagination metadata
     */
    public function getByProofing(string $proofingId, ?int $page = null, ?int $perPage = null)
    {
        $query = MemoraMediaSet::where('proof_uuid', $proofingId)
            ->withCount('media')
            ->orderBy('order');

        // Paginate the query
        $perPage = $perPage ?? 10;
        $paginator = $this->paginationService->paginate($query, $perPage, $page);

        // Transform items to resources
        $data = \App\Domains\Memora\Resources\V1\MediaSetResource::collection($paginator->items());

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
     * Delete a media set and all media in it
     */
    public function delete(string $selectionId, string $id): bool
    {
        $set = $this->find($selectionId, $id);
        
        // Load media relationship if not already loaded
        if (!$set->relationLoaded('media')) {
            $set->load(['media.feedback.replies', 'media.file']);
        }
        
        // Soft delete all media in this set, then delete the set in a transaction
        return DB::transaction(function () use ($set) {
            // Soft delete all media in this set
            // Loop through each media item to ensure soft deletes work correctly
            foreach ($set->media as $media) {
                $media->delete();
            }
            
            // Soft delete the set itself
            return $set->delete();
        });
    }

    /**
     * Get a media set
     */
    public function find(string $selectionId, string $id): MemoraMediaSet
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $set = MemoraMediaSet::where('selection_uuid', $selectionId)
            ->withCount('media')
            ->findOrFail($id);

        // Verify user owns the selection
        $selection = MemoraSelection::findOrFail($selectionId);
        if ($selection->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this selection');
        }

        return $set;
    }

    /**
     * Find a media set by proofing ID and set ID
     */
    public function findByProofing(string $proofingId, string $id): MemoraMediaSet
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $set = MemoraMediaSet::where('proof_uuid', $proofingId)
            ->where('uuid', $id)
            ->withCount('media')
            ->firstOrFail();

        // Verify user owns the proofing
        $proofing = \App\Domains\Memora\Models\MemoraProofing::findOrFail($proofingId);
        if ($proofing->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this proofing');
        }

        return $set;
    }

    /**
     * Reorder media sets
     */
    public function reorder(string $selectionId, array $setUuids): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify user owns the selection
        $selection = MemoraSelection::findOrFail($selectionId);
        if ($selection->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this selection');
        }

        // Update all set orders in a transaction
        return DB::transaction(function () use ($selectionId, $setUuids) {
            foreach ($setUuids as $order => $setUuid) {
                MemoraMediaSet::where('selection_uuid', $selectionId)
                    ->where('uuid', $setUuid)
                    ->update(['order' => $order]);
            }

            return true;
        });
    }

    /**
     * Update a media set
     */
    public function update(string $selectionId, string $id, array $data): MemoraMediaSet
    {
        $set = $this->find($selectionId, $id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['order'])) {
            $updateData['order'] = $data['order'];
        }
        // Handle selection_limit update (support both snake_case and camelCase)
        if (array_key_exists('selection_limit', $data) || array_key_exists('selectionLimit', $data)) {
            $limit = $data['selection_limit'] ?? $data['selectionLimit'] ?? null;
            // Explicitly set to null if null, empty string, or 0; otherwise cast to int
            if ($limit === null || $limit === '' || $limit === 0) {
                $updateData['selection_limit'] = null;
            } else {
                $updateData['selection_limit'] = (int) $limit;
            }
        }

        $set->update($updateData);

        return $set->fresh();
    }

    // ==================== Proofing Media Sets ====================

    /**
     * Create a media set in a proofing
     */
    public function createForProofing(string $proofingId, array $data): MemoraMediaSet
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $proofing = \App\Domains\Memora\Models\MemoraProofing::findOrFail($proofingId);

        // Verify user owns the proofing
        if ($proofing->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this proofing');
        }

        // Get the maximum order for sets in this proofing
        $maxOrder = MemoraMediaSet::where('proof_uuid', $proofingId)
            ->max('order') ?? -1;

        $setData = [
            'user_uuid' => Auth::user()->uuid,
            'proof_uuid' => $proofingId,
            'project_uuid' => $proofing->project_uuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'order' => $maxOrder + 1,
        ];

        return MemoraMediaSet::create($setData);
    }

    /**
     * Update a media set for proofing
     */
    public function updateForProofing(string $proofingId, string $id, array $data): MemoraMediaSet
    {
        $set = $this->findByProofing($proofingId, $id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['order'])) {
            $updateData['order'] = $data['order'];
        }

        $set->update($updateData);

        return $set->fresh();
    }

    /**
     * Delete a media set for proofing and all media in it
     */
    public function deleteForProofing(string $proofingId, string $id): bool
    {
        $set = $this->findByProofing($proofingId, $id);
        
        // Load media relationship if not already loaded
        if (!$set->relationLoaded('media')) {
            $set->load(['media.feedback.replies', 'media.file']);
        }
        
        // Soft delete all media in this set, then delete the set in a transaction
        return DB::transaction(function () use ($set) {
            // Soft delete all media in this set
            foreach ($set->media as $media) {
                $media->delete();
            }
            
            // Soft delete the set itself
            return $set->delete();
        });
    }

    /**
     * Reorder media sets for proofing
     */
    public function reorderForProofing(string $proofingId, array $setUuids): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify user owns the proofing
        $proofing = \App\Domains\Memora\Models\MemoraProofing::findOrFail($proofingId);
        if ($proofing->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this proofing');
        }

        // Update all set orders in a transaction
        return DB::transaction(function () use ($proofingId, $setUuids) {
            foreach ($setUuids as $order => $setUuid) {
                MemoraMediaSet::where('proof_uuid', $proofingId)
                    ->where('uuid', $setUuid)
                    ->update(['order' => $order]);
            }

            return true;
        });
    }
}

