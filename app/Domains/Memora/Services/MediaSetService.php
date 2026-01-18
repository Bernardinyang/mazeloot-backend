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
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $selection = MemoraSelection::findOrFail($selectionId);

        // Verify user owns the selection
        if ($selection->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this selection');
        }

        // Check if selection is completed
        if ($selection->status->value === 'completed') {
            throw new \RuntimeException('Cannot create sets for a completed selection');
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
     * @return array Paginated response with data and pagination metadata
     */
    public function getByProofing(string $proofingId, ?int $page = null, ?int $perPage = null, ?string $projectId = null)
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify user owns the proofing
        $proofingQuery = \App\Domains\Memora\Models\MemoraProofing::where('uuid', $proofingId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $proofingQuery->where('project_uuid', $projectId);
        }

        $proofingQuery->firstOrFail();

        $query = MemoraMediaSet::where('proof_uuid', $proofingId)
            ->withCount(['media' => function ($query) {
                $query->whereNull('deleted_at');
            }])
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
        if (! $set->relationLoaded('media')) {
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
        if (! $user) {
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
    public function findByProofing(string $proofingId, string $id, ?string $projectId = null): MemoraMediaSet
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $set = MemoraMediaSet::where('proof_uuid', $proofingId)
            ->where('uuid', $id)
            ->withCount('media')
            ->firstOrFail();

        // Verify user owns the proofing
        $query = \App\Domains\Memora\Models\MemoraProofing::where('uuid', $proofingId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $query->where('project_uuid', $projectId);
        }

        $proofing = $query->firstOrFail();

        return $set;
    }

    /**
     * Reorder media sets
     */
    public function reorder(string $selectionId, array $setUuids): bool
    {
        $user = Auth::user();
        if (! $user) {
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
    public function createForProofing(string $proofingId, array $data, ?string $projectId = null): MemoraMediaSet
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = \App\Domains\Memora\Models\MemoraProofing::where('uuid', $proofingId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $query->where('project_uuid', $projectId);
        }

        $proofing = $query->firstOrFail();

        // Check if proofing is completed
        if ($proofing->status->value === 'completed') {
            throw new \RuntimeException('Cannot create sets for a completed proofing');
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
    public function updateForProofing(string $proofingId, string $id, array $data, ?string $projectId = null): MemoraMediaSet
    {
        $set = $this->findByProofing($proofingId, $id, $projectId);

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
    public function deleteForProofing(string $proofingId, string $id, ?string $projectId = null): bool
    {
        $set = $this->findByProofing($proofingId, $id, $projectId);

        // Load media relationship if not already loaded
        if (! $set->relationLoaded('media')) {
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
    public function reorderForProofing(string $proofingId, array $setUuids, ?string $projectId = null): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify user owns the proofing
        $query = \App\Domains\Memora\Models\MemoraProofing::where('uuid', $proofingId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $query->where('project_uuid', $projectId);
        }

        $proofing = $query->firstOrFail();

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

    // ==================== Collection Media Sets ====================

    /**
     * Get all media sets for a collection with pagination
     *
     * @return array Paginated response with data and pagination metadata
     */
    public function getByCollection(string $collectionId, ?int $page = null, ?int $perPage = null, ?string $projectId = null)
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify user owns the collection
        $collectionQuery = \App\Domains\Memora\Models\MemoraCollection::where('uuid', $collectionId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $collectionQuery->where('project_uuid', $projectId);
        }

        $collectionQuery->firstOrFail();

        $query = MemoraMediaSet::where('collection_uuid', $collectionId)
            ->withCount(['media' => function ($query) {
                $query->whereNull('deleted_at');
            }])
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
     * Find a media set by collection ID and set ID
     */
    public function findByCollection(string $collectionId, string $id, ?string $projectId = null): MemoraMediaSet
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $set = MemoraMediaSet::where('collection_uuid', $collectionId)
            ->where('uuid', $id)
            ->withCount('media')
            ->firstOrFail();

        // Verify user owns the collection
        $query = \App\Domains\Memora\Models\MemoraCollection::where('uuid', $collectionId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $query->where('project_uuid', $projectId);
        }

        $query->firstOrFail();

        return $set;
    }

    /**
     * Create a media set in a collection
     */
    public function createForCollection(string $collectionId, array $data, ?string $projectId = null): MemoraMediaSet
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = \App\Domains\Memora\Models\MemoraCollection::where('uuid', $collectionId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $query->where('project_uuid', $projectId);
        }

        $collection = $query->firstOrFail();

        // Check if collection is completed
        if ($collection->status->value === 'completed') {
            throw new \RuntimeException('Cannot create sets for a completed collection');
        }

        // Get the maximum order for sets in this collection
        $maxOrder = MemoraMediaSet::where('collection_uuid', $collectionId)
            ->max('order') ?? -1;

        $setData = [
            'user_uuid' => Auth::user()->uuid,
            'collection_uuid' => $collectionId,
            'project_uuid' => $collection->project_uuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'order' => $maxOrder + 1,
        ];

        return MemoraMediaSet::create($setData);
    }

    /**
     * Update a media set for collection
     */
    public function updateForCollection(string $collectionId, string $id, array $data, ?string $projectId = null): MemoraMediaSet
    {
        $set = $this->findByCollection($collectionId, $id, $projectId);

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
     * Delete a media set for collection and all media in it
     */
    public function deleteForCollection(string $collectionId, string $id, ?string $projectId = null): bool
    {
        $set = $this->findByCollection($collectionId, $id, $projectId);

        // Load media relationship if not already loaded
        if (! $set->relationLoaded('media')) {
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
     * Reorder media sets for collection
     */
    public function reorderForCollection(string $collectionId, array $setUuids, ?string $projectId = null): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify user owns the collection
        $query = \App\Domains\Memora\Models\MemoraCollection::where('uuid', $collectionId)
            ->where('user_uuid', $user->uuid);

        if ($projectId !== null) {
            $query->where('project_uuid', $projectId);
        }

        $query->firstOrFail();

        // Update all set orders in a transaction
        return DB::transaction(function () use ($collectionId, $setUuids) {
            foreach ($setUuids as $order => $setUuid) {
                MemoraMediaSet::where('collection_uuid', $collectionId)
                    ->where('uuid', $setUuid)
                    ->update(['order' => $order]);
            }

            return true;
        });
    }

    // ==================== Raw File Media Sets ====================

    /**
     * Create a media set in a raw file
     */
    public function createForRawFile(string $rawFileId, array $data): MemoraMediaSet
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $rawFile = \App\Domains\Memora\Models\MemoraRawFile::findOrFail($rawFileId);

        // Verify user owns the raw file
        if ($rawFile->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this raw file');
        }

        // Check if raw file is completed
        if ($rawFile->status->value === 'completed') {
            throw new \RuntimeException('Cannot create sets for a completed raw file');
        }

        // Get the maximum order for sets in this raw file
        $maxOrder = MemoraMediaSet::where('raw_file_uuid', $rawFileId)
            ->max('order') ?? -1;

        $setData = [
            'user_uuid' => Auth::user()->uuid,
            'raw_file_uuid' => $rawFileId,
            'project_uuid' => $rawFile->project_uuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'order' => $maxOrder + 1,
        ];

        // Handle selection_limit (support both snake_case and camelCase)
        if (array_key_exists('selection_limit', $data) || array_key_exists('selectionLimit', $data)) {
            $limit = $data['selection_limit'] ?? $data['selectionLimit'] ?? null;
            if ($limit === null || $limit === '' || $limit === 0) {
                $setData['selection_limit'] = null;
            } else {
                $setData['selection_limit'] = (int) $limit;
            }
        }

        return MemoraMediaSet::create($setData);
    }

    /**
     * Get all media sets for a raw file with pagination
     */
    public function getByRawFile(string $rawFileId, ?int $page = null, ?int $perPage = null)
    {
        $query = MemoraMediaSet::where('raw_file_uuid', $rawFileId)
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
     * Find a media set by raw file ID and set ID
     */
    public function findByRawFile(string $rawFileId, string $id): MemoraMediaSet
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $set = MemoraMediaSet::where('raw_file_uuid', $rawFileId)
            ->where('uuid', $id)
            ->withCount('media')
            ->firstOrFail();

        // Verify user owns the raw file
        $rawFile = \App\Domains\Memora\Models\MemoraRawFile::findOrFail($rawFileId);
        if ($rawFile->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this raw file');
        }

        return $set;
    }

    /**
     * Update a media set for raw file
     */
    public function updateForRawFile(string $rawFileId, string $id, array $data): MemoraMediaSet
    {
        $set = $this->findByRawFile($rawFileId, $id);

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
            if ($limit === null || $limit === '' || $limit === 0) {
                $updateData['selection_limit'] = null;
            } else {
                $updateData['selection_limit'] = (int) $limit;
            }
        }

        $set->update($updateData);

        return $set->fresh();
    }

    /**
     * Delete a media set for raw file
     */
    public function deleteForRawFile(string $rawFileId, string $id): bool
    {
        $set = $this->findByRawFile($rawFileId, $id);

        // Load media relationship if not already loaded
        if (! $set->relationLoaded('media')) {
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
     * Reorder media sets for raw file
     */
    public function reorderForRawFile(string $rawFileId, array $setUuids): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify user owns the raw file
        $rawFile = \App\Domains\Memora\Models\MemoraRawFile::findOrFail($rawFileId);
        if ($rawFile->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this raw file');
        }

        // Update all set orders in a transaction
        return DB::transaction(function () use ($rawFileId, $setUuids) {
            foreach ($setUuids as $order => $setUuid) {
                MemoraMediaSet::where('raw_file_uuid', $rawFileId)
                    ->where('uuid', $setUuid)
                    ->update(['order' => $order]);
            }

            return true;
        });
    }
}
