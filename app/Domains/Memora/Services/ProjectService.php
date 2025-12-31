<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Services\Pagination\PaginationService;
use Illuminate\Support\Facades\Auth;

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
     * @return array Paginated response with data and pagination metadata
     */
    public function list(array $filters = [], ?int $page = null, ?int $perPage = null)
    {
        $user = Auth::user();
        $query = MemoraProject::query()->with([
            'mediaSets',
            'starredByUsers' => function ($query) use ($user) {
                if ($user) {
                    $query->where('user_uuid', $user->uuid);
                }
            },
        ]);

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Search
        if (isset($filters['search']) && ! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        if (isset($filters['sortBy']) && ! empty($filters['sortBy'])) {
            $this->applySorting($query, $filters['sortBy']);
        } else {
            // Default ordering
            $query->orderBy('created_at', 'desc');
        }

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
     */
    public function find(string $id): MemoraProject
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $project = MemoraProject::with([
            'mediaSets',
            'starredByUsers' => function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            },
            'selection' => function ($query) {
                $query->with(['mediaSets' => function ($q) {
                    $q->withCount(['media' => function ($mediaQuery) {
                        $mediaQuery->whereNull('deleted_at');
                    }])->orderBy('order');
                }])
                // Add subqueries for media counts (excluding soft-deleted media and media sets)
                    ->addSelect([
                        'media_count' => \App\Domains\Memora\Models\MemoraMedia::query()
                            ->selectRaw('COALESCE(COUNT(*), 0)')
                            ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                            ->whereColumn('memora_media_sets.selection_uuid', 'memora_selections.uuid')
                            ->whereNull('memora_media.deleted_at')
                            ->whereNull('memora_media_sets.deleted_at')
                            ->limit(1),
                        'selected_count' => \App\Domains\Memora\Models\MemoraMedia::query()
                            ->selectRaw('COALESCE(COUNT(*), 0)')
                            ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                            ->whereColumn('memora_media_sets.selection_uuid', 'memora_selections.uuid')
                            ->where('memora_media.is_selected', true)
                            ->whereNull('memora_media.deleted_at')
                            ->whereNull('memora_media_sets.deleted_at')
                            ->limit(1),
                    ]);
            },
            'proofing' => function ($query) {
                $query->with(['mediaSets' => function ($q) {
                    $q->withCount(['media' => function ($mediaQuery) {
                        $mediaQuery->whereNull('deleted_at');
                    }])->orderBy('order');
                }])
                // Add subqueries for media counts (excluding soft-deleted media and media sets)
                    ->addSelect([
                        'media_count' => \App\Domains\Memora\Models\MemoraMedia::query()
                            ->selectRaw('COALESCE(COUNT(*), 0)')
                            ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                            ->whereColumn('memora_media_sets.proof_uuid', 'memora_proofing.uuid')
                            ->whereNull('memora_media.deleted_at')
                            ->whereNull('memora_media_sets.deleted_at')
                            ->limit(1),
                    ]);
            },
            'collection',
        ])->findOrFail($id);

        // Verify user owns the project
        if ($project->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this project');
        }

        // Map the subquery results to the expected attribute names for selections
        if ($project->selection) {
            $selection = $project->selection;
            $selection->setAttribute('media_count', (int) ($selection->media_count ?? 0));
            $selection->setAttribute('selected_count', (int) ($selection->selected_count ?? 0));
            // Set set count from loaded relationship
            if ($selection->relationLoaded('mediaSets')) {
                $selection->setAttribute('set_count', $selection->mediaSets->count());
            }
        }

        // Map the subquery results to the expected attribute names for proofing
        if ($project->proofing) {
            $proofing = $project->proofing;
            $proofing->setAttribute('media_count', (int) ($proofing->media_count ?? 0));
            // Set set count from loaded relationship
            if ($proofing->relationLoaded('mediaSets')) {
                $proofing->setAttribute('set_count', $proofing->mediaSets->count());
            }
        }

        return $project;
    }

    /**
     * Create a new project
     */
    public function create(array $data, string $userUuid): MemoraProject
    {
        $project = MemoraProject::create([
            'user_uuid' => $userUuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'has_selections' => $data['hasSelections'] ?? false,
            'has_proofing' => $data['hasProofing'] ?? false,
            'has_collections' => $data['hasCollections'] ?? false,
            'preset_uuid' => $data['presetId'] ?? null,
            'watermark_uuid' => $data['watermarkId'] ?? null,
            'settings' => $data['settings'] ?? [],
            'color' => $data['color'] ?? '#3B82F6',
        ]);

        // Create media sets if provided
        if (isset($data['mediaSets']) && is_array($data['mediaSets'])) {
            foreach ($data['mediaSets'] as $setData) {
                MemoraMediaSet::create([
                    'project_uuid' => $project->uuid,
                    'name' => $setData['name'],
                    'description' => $setData['description'] ?? null,
                    'order' => $setData['order'] ?? 0,
                ]);
            }
        }

        $projectColor = $project->color ?? '#3B82F6';

        // Create selection phase if enabled
        if ($data['hasSelections'] ?? false) {
            $selectionService = app(\App\Domains\Memora\Services\SelectionService::class);
            $selectionSettings = $data['selectionSettings'] ?? [];
            $selectionService->create([
                'project_uuid' => $project->uuid,
                'name' => $selectionSettings['name'] ?? 'Selections',
                'description' => $selectionSettings['description'] ?? null,
                'color' => $projectColor,
                'selection_limit' => $selectionSettings['selectionLimit'] ?? 0,
            ]);
        }

        // Create proofing phase if enabled
        if ($data['hasProofing'] ?? false) {
            $proofingService = app(\App\Domains\Memora\Services\ProofingService::class);
            $proofingSettings = $data['proofingSettings'] ?? [];
            $proofingService->create([
                'project_uuid' => $project->uuid,
                'name' => $proofingSettings['name'] ?? 'Proofing',
                'maxRevisions' => $proofingSettings['maxRevisions'] ?? 5,
                'color' => $projectColor,
            ]);
        }

        // Create collection phase if enabled
        if ($data['hasCollections'] ?? false) {
            $collectionService = app(\App\Domains\Memora\Services\CollectionService::class);
            $collectionSettings = $data['collectionSettings'] ?? [];
            $collectionService->create($project->uuid, [
                'name' => $collectionSettings['name'] ?? 'Collections',
                'description' => $collectionSettings['description'] ?? null,
                'color' => $projectColor,
            ]);
        }

        return $project->load('mediaSets');
    }

    /**
     * Update a project
     */
    public function update(string $id, array $data): MemoraProject
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $project = MemoraProject::findOrFail($id);

        // Verify user owns the project
        if ($project->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this project');
        }

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
        if (isset($data['settings'])) {
            $updateData['settings'] = $data['settings'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['presetId'])) {
            $updateData['preset_uuid'] = $data['presetId'];
        }
        if (isset($data['watermarkId'])) {
            $updateData['watermark_uuid'] = $data['watermarkId'];
        }
        if (isset($data['eventDate'])) {
            // Store event date in settings or as a separate field if migration exists
            // For now, we'll store it in settings
            $settings = $updateData['settings'] ?? $project->settings ?? [];
            if ($data['eventDate'] === null) {
                unset($settings['eventDate']);
            } else {
                $settings['eventDate'] = $data['eventDate'];
            }
            $updateData['settings'] = $settings;
        }

        // Update phase flags if provided
        if (isset($data['hasSelections'])) {
            $updateData['has_selections'] = $data['hasSelections'];
        }
        if (isset($data['hasProofing'])) {
            $updateData['has_proofing'] = $data['hasProofing'];
        }
        if (isset($data['hasCollections'])) {
            $updateData['has_collections'] = $data['hasCollections'];
        }

        $project->update($updateData);

        // Update media sets if provided
        if (isset($data['mediaSets']) && is_array($data['mediaSets'])) {
            // Handle media sets update logic
            // This is a simplified version - full implementation would handle adds/updates/deletes
        }

        // Update phase settings if provided
        if (isset($data['selectionSettings']) && $project->has_selections) {
            $selectionService = app(\App\Domains\Memora\Services\SelectionService::class);
            $selection = $project->selection;
            if ($selection) {
                $updateData = [];
                if (isset($data['selectionSettings']['name'])) {
                    $updateData['name'] = $data['selectionSettings']['name'];
                }
                if (isset($data['selectionSettings']['description'])) {
                    $updateData['description'] = $data['selectionSettings']['description'];
                }
                if (isset($data['selectionSettings']['selectionLimit'])) {
                    $updateData['selectionLimit'] = $data['selectionSettings']['selectionLimit'];
                }
                if (! empty($updateData)) {
                    $selectionService->update($selection->uuid, $updateData);
                }
            }
        }

        if (isset($data['proofingSettings']) && $project->has_proofing) {
            $proofingService = app(\App\Domains\Memora\Services\ProofingService::class);
            $project->load('proofing');
            $proofing = $project->proofing;
            if ($proofing) {
                $updateData = [];
                if (isset($data['proofingSettings']['name'])) {
                    $updateData['name'] = $data['proofingSettings']['name'];
                }
                if (array_key_exists('description', $data['proofingSettings'])) {
                    $desc = $data['proofingSettings']['description'];
                    if ($desc === null || $desc === '') {
                        $updateData['description'] = null;
                    } else {
                        $updateData['description'] = trim((string) $desc);
                    }
                }
                if (isset($data['proofingSettings']['maxRevisions'])) {
                    $updateData['maxRevisions'] = $data['proofingSettings']['maxRevisions'];
                }
                if (! empty($updateData)) {
                    $proofingService->update($project->uuid, $proofing->uuid, $updateData);
                }
            }
        }

        if (isset($data['collectionSettings']) && $project->has_collections) {
            // Collections don't have a single entity to update, they're multiple collections
            // This would need to be handled differently if needed
        }

        return $project->load('mediaSets');
    }

    /**
     * Delete a project and all related data
     * This will cascade delete:
     * - Selections (and their media sets, media, starred selections)
     * - Proofing (and their media sets, media)
     * - Collections
     * - Media sets (and their media, starred media)
     * - Media (and their starred media, feedback)
     */
    public function delete(string $id): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $project = MemoraProject::findOrFail($id);

        // Verify user owns the project
        if ($project->user_uuid !== $user->uuid) {
            throw new \Exception('Unauthorized: You do not own this project');
        }

        // Use forceDelete to actually delete from database
        // This will trigger database cascade deletes for all foreign keys
        // which will automatically delete:
        // - All selections (cascade from project_uuid)
        // - All proofing (cascade from project_uuid)
        // - All collections (cascade from project_uuid)
        // - All media sets (cascade from project_uuid, selection_uuid, proof_uuid)
        // - All media (cascade from media_set_uuid)
        // - All starred selections (cascade from selection_uuid)
        // - All starred media (cascade from media_uuid)
        // - All media feedback (cascade from media_uuid)
        return $project->forceDelete();
    }

    /**
     * Get project phases
     */
    public function getPhases(string $id): array
    {
        $project = MemoraProject::findOrFail($id);

        $selection = $project->selection;
        $proofing = $project->proofing;
        $collection = $project->collection;

        return [
            'selection' => $selection ? [
                'id' => $selection->uuid,
                'projectId' => $selection->project_uuid,
                'name' => $selection->name,
                'status' => $selection->status?->value ?? $selection->status,
                'selectionCompletedAt' => $selection->selection_completed_at?->toIso8601String(),
                'autoDeleteDate' => $selection->auto_delete_date?->toIso8601String(),
                'createdAt' => $selection->created_at->toIso8601String(),
                'updatedAt' => $selection->updated_at->toIso8601String(),
            ] : null,
            'proofing' => $proofing ? [
                'id' => $proofing->uuid,
                'projectId' => $proofing->project_uuid,
                'name' => $proofing->name,
                'status' => $proofing->status?->value ?? $proofing->status,
                'maxRevisions' => $proofing->max_revisions,
                'currentRevision' => $proofing->current_revision,
                'createdAt' => $proofing->created_at->toIso8601String(),
                'updatedAt' => $proofing->updated_at->toIso8601String(),
            ] : null,
            'collection' => $collection ? [
                'id' => $collection->uuid,
                'projectId' => $collection->project_uuid,
                'name' => $collection->name,
                'status' => $collection->status?->value ?? $collection->status,
                'createdAt' => $collection->created_at->toIso8601String(),
                'updatedAt' => $collection->updated_at->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Toggle star status for a project
     *
     * @param  string  $id  Project UUID
     * @return array{starred: bool} Returns whether the project is now starred
     */
    public function toggleStar(string $id): array
    {
        $project = MemoraProject::findOrFail($id);
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('User not authenticated');
        }

        // Toggle the star relationship
        $user->starredProjects()->toggle($project->uuid);

        // Check if it's now starred
        $isStarred = $user->starredProjects()->where('project_uuid', $project->uuid)->exists();

        return [
            'starred' => $isStarred,
        ];
    }

    /**
     * Apply sorting to projects query based on sortBy parameter
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $sortBy  Format: 'field-direction' (e.g., 'created-desc', 'name-asc')
     */
    protected function applySorting($query, string $sortBy): void
    {
        $parts = explode('-', $sortBy);
        $field = $parts[0] ?? 'created';
        $direction = strtoupper($parts[1] ?? 'desc');

        // Validate direction
        if (! in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'DESC';
        }

        // Map frontend sort values to database fields
        $fieldMap = [
            'created' => 'created_at',
            'name' => 'name',
        ];

        $dbField = $fieldMap[$field] ?? 'created_at';

        $query->orderBy($dbField, $direction);
    }
}
