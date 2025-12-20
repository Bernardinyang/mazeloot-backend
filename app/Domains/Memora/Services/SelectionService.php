<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Resources\V1\SelectionResource;
use App\Services\Pagination\PaginationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SelectionService
{
    protected PaginationService $paginationService;

    public function __construct(PaginationService $paginationService)
    {
        $this->paginationService = $paginationService;
    }
    /**
     * Create a selection (standalone or project-based based on project_uuid in data)
     */
    public function create(array $data): SelectionResource
    {
        $projectUuid = $data['project_uuid'] ?? null;

        if ($projectUuid) {
            MemoraProject::query()->findOrFail($projectUuid);
        }

        $selectionData = [
            'user_uuid' => Auth::user()->uuid,
            'project_uuid' => $projectUuid,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#10B981',
        ];

        if (!empty($data['password'])) {
            $selectionData['password'] = bcrypt($data['password']);
        }

        $selection = MemoraSelection::query()->create($selectionData);
        return new SelectionResource($this->findModel($selection->uuid));
    }

    /**
     * Get a selection model by ID (internal use)
     *
     * @param string $id Selection UUID
     * @return MemoraSelection
     */
    protected function findModel(string $id): MemoraSelection
    {
        $selection = MemoraSelection::query()->where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->with(['starredByUsers' => function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }])
            // Add subquery for media count to avoid N+1
            ->addSelect([
                'media_count' => MemoraMedia::query()->selectRaw('COUNT(*)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.selection_uuid', 'memora_selections.uuid')
                    ->limit(1),
                'selected_count' => MemoraMedia::query()->selectRaw('COUNT(*)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.selection_uuid', 'memora_selections.uuid')
                    ->where('memora_media.is_selected', true)
                    ->limit(1),
            ])
            ->firstOrFail();

        // Map the subquery results to the expected attribute names
        $selection->setAttribute('media_count', (int)($selection->media_count ?? 0));
        $selection->setAttribute('selected_count', (int)($selection->selected_count ?? 0));

        return $selection;
    }

    /**
     * Get all selections with optional search, sort, filter, and pagination parameters
     *
     * @param string|null $projectUuid Filter by project UUID
     * @param string|null $search Search query (searches in name)
     * @param string|null $sortBy Sort field and direction (e.g., 'created-desc', 'name-asc', 'status-asc')
     * @param string|null $status Filter by status (e.g., 'draft', 'completed', 'active')
     * @param bool|null $starred Filter by starred status
     * @param int $page Page number (default: 1)
     * @param int $perPage Items per page (default: 50)
     * @return array Paginated response with data and pagination metadata
     */
    public function getAll(
        ?string $projectUuid = null,
        ?string $search = null,
        ?string $sortBy = null,
        ?string $status = null,
        ?bool   $starred = null,
        int $page = 1,
        int $perPage = 50
    ): array
    {
        $query = MemoraSelection::query()->where('user_uuid', Auth::user()->uuid)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->with(['starredByUsers' => function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }])
            // Add subqueries for media counts to avoid N+1 queries
            ->addSelect([
                'media_count' => MemoraMedia::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.selection_uuid', 'memora_selections.uuid')
                    ->limit(1),
                'selected_count' => MemoraMedia::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.selection_uuid', 'memora_selections.uuid')
                    ->where('memora_media.is_selected', true)
                    ->limit(1),
            ]);

        // Filter by project UUID
        if ($projectUuid) {
            $query->where('project_uuid', $projectUuid);
        }

        // Search by name
        if ($search && trim($search)) {
            $query->where('name', 'LIKE', '%' . trim($search) . '%');
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by starred status
        if ($starred !== null) {
            if ($starred) {
                // Only get selections that are starred by the current user
                $query->whereHas('starredByUsers', function ($q) {
                    $q->where('user_uuid', Auth::user()->uuid);
                });
            } else {
                // Only get selections that are NOT starred by the current user
                $query->whereDoesntHave('starredByUsers', function ($q) {
                    $q->where('user_uuid', Auth::user()->uuid);
                });
            }
        }

        // Apply sorting
        if ($sortBy) {
            $this->applySorting($query, $sortBy);
        } else {
            // Default sort: created_at desc
            $query->orderBy('created_at', 'desc');
        }

        // Paginate the query
        $paginator = $this->paginationService->paginate($query, $perPage, $page);

        // Map the subquery results to the expected attribute names
        foreach ($paginator->items() as $selection) {
            $selection->setAttribute('media_count', (int)($selection->media_count ?? 0));
            $selection->setAttribute('selected_count', (int)($selection->selected_count ?? 0));
        }

        // Transform items to resources
        $data = SelectionResource::collection($paginator->items());

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
     * Apply sorting to the query based on sortBy parameter
     *
     * @param Builder $query
     * @param string $sortBy Format: 'field-direction' (e.g., 'created-desc', 'name-asc')
     */
    protected function applySorting(Builder $query, string $sortBy): void
    {
        $parts = explode('-', $sortBy);
        $field = $parts[0] ?? 'created_at';
        $direction = strtoupper($parts[1] ?? 'desc');

        // Validate direction
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'DESC';
        }

        // Map frontend sort values to database fields
        $fieldMap = [
            'created' => 'created_at',
            'name' => 'name',
            'status' => 'status',
        ];

        $dbField = $fieldMap[$field] ?? 'created_at';

        $query->orderBy($dbField, $direction);
    }

    /**
     * Publish a selection (creative can only publish to active, not complete)
     */
    public function publish(string $id): SelectionResource
    {
        $selection = MemoraSelection::where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        $selection->update([
            'status' => 'active',
        ]);

        // Return with calculated counts
        return $this->find($id);
    }

    /**
     * Update a selection
     */
    public function update(string $id, array $data): SelectionResource
    {
        $selection = MemoraSelection::query()->where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['color'])) $updateData['color'] = $data['color'];
        if (isset($data['cover_photo_url'])) $updateData['cover_photo_url'] = $data['cover_photo_url'];

        // Handle password update - hash if provided, or set to null if empty string
        if (array_key_exists('password', $data)) {
            if (!empty($data['password'])) {
                $updateData['password'] = bcrypt($data['password']);
            } else {
                $updateData['password'] = null;
            }
        }

        $selection->update($updateData);

        // Return with calculated counts
        return $this->find($id);
    }

    /**
     * Get a selection by ID (works for both standalone and project-based)
     * Returns a resource for API responses
     */
    public function find(string $id): SelectionResource
    {
        return new SelectionResource($this->findModel($id));
    }

    /**
     * Complete a selection (only for guests)
     * Marks the provided media UUIDs as selected when completing
     */
    public function complete(string $id, array $mediaIds, ?string $completedByEmail = null): SelectionResource
    {
        $query = MemoraSelection::query()->where('uuid', $id)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }]);

        // Only load starred relationship if user is authenticated
        if (Auth::check()) {
            $query->with(['starredByUsers' => function ($q) {
                $q->where('user_uuid', Auth::user()->uuid);
            }]);
        }

        $selection = $query->firstOrFail();

        // Mark the provided media as selected
        MemoraMedia::query()->whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->whereIn('uuid', $mediaIds)
            ->update([
                'is_selected' => true,
                'selected_at' => now(),
            ]);

        $selection->update([
            'status' => 'completed',
            'selection_completed_at' => now(),
            'completed_by_email' => $completedByEmail,
            'auto_delete_date' => now()->addDays(30), // 30 days auto-delete
        ]);

        return new SelectionResource($this->findModel($id));
    }

    /**
     * Recover deleted media
     */
    public function recover(string $id, array $mediaIds): array
    {
        $selection = $this->findModel($id);

        $recovered = MemoraMedia::query()->whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->whereIn('uuid', $mediaIds)
            ->withTrashed() // If soft deletes are enabled
            ->restore();

        return [
            'recoveredCount' => count($mediaIds),
        ];
    }

    /**
     * Get selected media
     */
    public function getSelectedMedia(string $id, ?string $setUuid = null): Collection
    {
        $query = MemoraMedia::query()->whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->where('is_selected', true)
            ->orderBy('order');

        if ($setUuid) {
            $query->where('media_set_uuid', $setUuid);
        }

        return $query->get();
    }

    /**
     * Get selected filenames
     */
    public function getSelectedFilenames(string $id): array
    {
        $filenames = MemoraMedia::query()->whereHas('mediaSet', function ($query) use ($id) {
            $query->where('selection_uuid', $id);
        })
            ->where('is_selected', true)
            ->orderBy('order')
            ->pluck('filename')
            ->toArray();

        return [
            'filenames' => $filenames,
            'count' => count($filenames),
        ];
    }

    /**
     * Toggle star status for a selection
     *
     * @param string $id Selection UUID
     * @return array{starred: bool} Returns whether the selection is now starred
     */
    public function toggleStar(string $id): array
    {
        $selection = $this->findModel($id);
        $user = Auth::user();

        // Toggle the star relationship
        $user->starredSelections()->toggle($selection->uuid);

        // Check if it's now starred
        $isStarred = $user->starredSelections()->where('selection_uuid', $selection->uuid)->exists();

        return [
            'starred' => $isStarred,
        ];
    }

    /**
     * Auto-delete selections that have passed their auto_delete_date
     * Only deletes selected media (is_selected = true), keeping unselected media and selection intact
     *
     * @return array{selected_media_deleted: int}
     */
    public function autoDeleteExpiredSelections(): array
    {
        $expiredSelections = MemoraSelection::query()
            ->whereNotNull('auto_delete_date')
            ->where('auto_delete_date', '<=', now())
            ->get();

        $selectedMediaDeleted = 0;

        foreach ($expiredSelections as $selection) {
            try {
                // Get all selected media in this selection (only where is_selected = true)
                $selectedMedia = MemoraMedia::query()
                    ->whereHas('mediaSet', function ($query) use ($selection) {
                        $query->where('selection_uuid', $selection->uuid);
                    })
                    ->where('is_selected', true)
                    ->get();

                // Delete only the selected media (not unselected media)
                foreach ($selectedMedia as $media) {
                    try {
                        $media->delete();
                        $selectedMediaDeleted++;
                    } catch (\Exception $e) {
                        Log::error(
                            "Failed to auto-delete selected media {$media->uuid}: " . $e->getMessage()
                        );
                    }
                }

                // Clear the auto_delete_date since we've processed the selected media
                // This prevents it from being processed again
                // The selection remains intact regardless of remaining media
                $selection->update(['auto_delete_date' => null]);
            } catch (\Exception $e) {
                // Log error but continue with other selections
                Log::error(
                    "Failed to auto-delete expired selection {$selection->uuid}: " . $e->getMessage()
                );
            }
        }

        return [
            'selected_media_deleted' => $selectedMediaDeleted,
        ];
    }

    /**
     * Delete a selection
     */
    public function delete(string $id): bool
    {
        return $this->findModel($id)->delete();
    }
}
