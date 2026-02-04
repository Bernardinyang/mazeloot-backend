<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Resources\V1\SelectionResource;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Notification\NotificationService;
use App\Services\Pagination\PaginationService;
use App\Support\MemoraFrontendUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SelectionService
{
    protected PaginationService $paginationService;

    protected NotificationService $notificationService;

    protected ActivityLogService $activityLogService;

    public function __construct(
        PaginationService $paginationService,
        NotificationService $notificationService,
        ActivityLogService $activityLogService
    ) {
        $this->paginationService = $paginationService;
        $this->notificationService = $notificationService;
        $this->activityLogService = $activityLogService;
    }

    /**
     * Create a selection (standalone or project-based based on project_uuid in data)
     */
    public function create(array $data): SelectionResource
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        if (! $user->isAdmin()) {
            $selectionLimit = app(\App\Services\Subscription\TierService::class)->getSelectionLimit($user);
            if ($selectionLimit !== null) {
                $currentCount = MemoraSelection::where('user_uuid', $user->uuid)->count();
                if ($currentCount >= $selectionLimit) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'limit' => ['Selection limit reached. Upgrade your plan for more selections.'],
                    ]);
                }
            }
        }

        $projectUuid = $data['project_uuid'] ?? null;

        if ($projectUuid) {
            MemoraProject::query()->findOrFail($projectUuid);
        }

        $selectionData = [
            'user_uuid' => $user->uuid,
            'project_uuid' => $projectUuid,
            'name' => $data['name'],
            'description' => (isset($data['description']) && trim($data['description']) !== '') ? trim($data['description']) : null,
            'color' => $data['color'] ?? '#10B981',
        ];

        if (! empty($data['password'])) {
            $selectionData['password'] = $data['password'];
        }

        if (isset($data['selection_limit']) || isset($data['selectionLimit'])) {
            $selectionData['selection_limit'] = $data['selection_limit'] ?? $data['selectionLimit'] ?? null;
        }

        $selection = MemoraSelection::query()->create($selectionData);

        $this->notificationService->create(
            $user->uuid,
            'memora',
            'selection_created',
            'Selection Created',
            "Selection '{$selection->name}' has been created successfully.",
            "Your new selection '{$selection->name}' is now available to use.",
            null,
            MemoraFrontendUrls::selectionDetailPath($selection->uuid, $selection->project_uuid),
            ['coverPhoto' => $selection->cover_photo_url]
        );

        $this->activityLogService->log(
            'created',
            $selection,
            "Created selection phase '{$selection->name}'",
            [
                'phase_type' => 'selection',
                'project_uuid' => $selection->project_uuid,
                'selection_uuid' => $selection->uuid,
            ],
            $user
        );

        return new SelectionResource($this->findModel($selection->uuid));
    }


    /**
     * Get a selection model by ID (internal use)
     *
     * @param  string  $id  Selection UUID
     */
    protected function findModel(string $id): MemoraSelection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $selection = MemoraSelection::query()->where('user_uuid', $user->uuid)
            ->where('uuid', $id)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->with(['starredByUsers' => function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
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
        $selection->setAttribute('media_count', (int) ($selection->media_count ?? 0));
        $selection->setAttribute('selected_count', (int) ($selection->selected_count ?? 0));

        return $selection;
    }

    /**
     * Get all selections with optional search, sort, filter, and pagination parameters
     *
     * @param  string|null  $projectUuid  Filter by project UUID
     * @param  string|null  $search  Search query (searches in name)
     * @param  string|null  $sortBy  Sort field and direction (e.g., 'created-desc', 'name-asc', 'status-asc')
     * @param  string|null  $status  Filter by status (e.g., 'draft', 'completed', 'active')
     * @param  bool|null  $starred  Filter by starred status
     * @param  int  $page  Page number (default: 1)
     * @param  int  $perPage  Items per page (default: 10)
     * @return array Paginated response with data and pagination metadata
     */
    public function getAll(
        ?string $projectUuid = null,
        ?string $search = null,
        ?string $sortBy = null,
        ?string $status = null,
        ?bool $starred = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $query = MemoraSelection::query()->where('user_uuid', Auth::user()->uuid)
            ->with(['project'])
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
            $query->where('name', 'LIKE', '%'.trim($search).'%');
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
            $selection->setAttribute('media_count', (int) ($selection->media_count ?? 0));
            $selection->setAttribute('selected_count', (int) ($selection->selected_count ?? 0));
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
     * @param  string  $sortBy  Format: 'field-direction' (e.g., 'created-desc', 'name-asc')
     */
    protected function applySorting(Builder $query, string $sortBy): void
    {
        $parts = explode('-', $sortBy);
        $field = $parts[0] ?? 'created_at';
        $direction = strtoupper($parts[1] ?? 'desc');

        // Validate direction
        if (! in_array($direction, ['ASC', 'DESC'])) {
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

    public function publish(string $id): SelectionResource
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $selection = MemoraSelection::where('user_uuid', $user->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        $newStatus = match ($selection->status->value) {
            'draft' => 'active',
            'active' => 'draft',
            'completed' => 'active',
            default => 'active',
        };

        // Validate that at least one email is in allowed_emails before publishing to active
        if ($newStatus === 'active') {
            $allowedEmails = $selection->allowed_emails ?? [];
            if (empty($allowedEmails) || ! is_array($allowedEmails) || count(array_filter($allowedEmails)) === 0) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['allowed_emails' => ['At least one email address must be added to "Allowed Emails" before publishing the selection.']]
                );
            }
        }

        $wasCompleted = $selection->status->value === 'completed';
        $selection->update(['status' => $newStatus]);

        $selection->refresh();
        $selection->load(['mediaSets' => function ($query) {
            $query->withCount('media')->orderBy('order');
        }]);
        $selection->load(['starredByUsers' => function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        }]);

        // Notify the selection owner when republishing a completed selection
        if ($wasCompleted && $newStatus === 'active') {
            try {
                $this->notificationService->create(
                    $user->uuid,
                    'memora',
                    'selection_republished',
                    'Selection Republished',
                    "Selection '{$selection->name}' has been republished.",
                    "Selection '{$selection->name}' has been republished and is now available to clients.",
                    null,
                    MemoraFrontendUrls::selectionDetailPath($selection->uuid, $selection->project_uuid),
                    ['coverPhoto' => $selection->cover_photo_url]
                );
            } catch (\Exception $e) {
                Log::error('Failed to send selection republish notification', [
                    'selection_uuid' => $selection->uuid,
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Log activity for selection publish
        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
            action: $newStatus === 'active' ? 'selection_published' : 'selection_unpublished',
            subject: $selection,
            description: "Selection '{$selection->name}' {$newStatus}.",
            properties: [
                'selection_uuid' => $selection->uuid,
                'selection_name' => $selection->name,
                'project_uuid' => $selection->project_uuid,
                'status' => $newStatus,
            ],
            causer: $user
        );

        return new SelectionResource($selection);
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
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        // Always handle description if it exists in the data (even if null)
        if (array_key_exists('description', $data)) {
            $desc = $data['description'];
            if ($desc === null || $desc === '') {
                $updateData['description'] = null;
            } else {
                $updateData['description'] = trim((string) $desc);
            }
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['cover_photo_url'])) {
            $updateData['cover_photo_url'] = $data['cover_photo_url'];
        }

        // Handle cover_focal_point update (support both snake_case and camelCase)
        if (array_key_exists('cover_focal_point', $data) || array_key_exists('coverFocalPoint', $data)) {
            $focalPoint = $data['cover_focal_point'] ?? $data['coverFocalPoint'] ?? null;
            if ($focalPoint !== null && is_array($focalPoint) && isset($focalPoint['x'], $focalPoint['y'])) {
                $updateData['cover_focal_point'] = [
                    'x' => (float) $focalPoint['x'],
                    'y' => (float) $focalPoint['y'],
                ];
            } else {
                $updateData['cover_focal_point'] = null;
            }
        }

        // Handle password update - store in plain text if provided, or set to null if empty string
        if (array_key_exists('password', $data)) {
            if (! empty($data['password'])) {
                $updateData['password'] = $data['password'];
            } else {
                $updateData['password'] = null;
            }
        }

        // Handle allowed_emails update
        if (array_key_exists('allowed_emails', $data) || array_key_exists('allowedEmails', $data)) {
            $emails = $data['allowed_emails'] ?? $data['allowedEmails'] ?? [];
            // Ensure it's an array and filter out empty values
            $emails = is_array($emails) ? array_filter(array_map('trim', $emails)) : [];
            $updateData['allowed_emails'] = ! empty($emails) ? array_values($emails) : null;
        }

        // Handle selection_limit update (support both snake_case and camelCase)
        if (array_key_exists('selection_limit', $data) || array_key_exists('selectionLimit', $data)) {
            $limit = $data['selection_limit'] ?? $data['selectionLimit'] ?? null;
            $updateData['selection_limit'] = $limit !== null ? (int) $limit : null;
        }

        // Handle auto_delete_date update
        if (array_key_exists('auto_delete_date', $data)) {
            $updateData['auto_delete_date'] = $data['auto_delete_date'] ? $data['auto_delete_date'] : null;
        }

        // Handle settings updates (typographyDesign and galleryAssist)
        $needsSettingsUpdate = false;
        // Always start with existing settings to preserve all existing values
        $settings = $selection->settings ?? [];

        // Handle typographyDesign - always merge with defaults
        if (isset($data['typographyDesign'])) {
            if (! isset($settings['design'])) {
                $settings['design'] = [];
            }
            $defaults = [
                'fontFamily' => 'sans',
                'fontStyle' => 'normal',
            ];
            $settings['design']['typography'] = array_merge($defaults, $data['typographyDesign']);
            $needsSettingsUpdate = true;
        }

        // Handle galleryAssist
        if (array_key_exists('galleryAssist', $data)) {
            if (! isset($settings['general'])) {
                $settings['general'] = [];
            }
            $settings['general']['galleryAssist'] = (bool) $data['galleryAssist'];
            // Also set at root level for backward compatibility
            $settings['galleryAssist'] = (bool) $data['galleryAssist'];
            $needsSettingsUpdate = true;
        }

        if ($needsSettingsUpdate) {
            $updateData['settings'] = $settings;
        }

        // Validate that at least one email is in allowed_emails before changing status to active
        $newStatus = $updateData['status'] ?? $selection->status->value;
        if ($newStatus === 'active') {
            // Get the current allowed_emails (either from update data or existing selection)
            $allowedEmails = $updateData['allowed_emails'] ?? $selection->allowed_emails ?? [];
            if (empty($allowedEmails) || ! is_array($allowedEmails) || count(array_filter($allowedEmails)) === 0) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['allowed_emails' => ['At least one email address must be added to "Allowed Emails" before publishing the selection.']]
                );
            }
        }

        $selection->update($updateData);
        $selection->refresh();

        $user = Auth::user();
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'selection_updated',
            'Selection Updated',
            "Selection '{$selection->name}' has been updated successfully.",
            "Your selection '{$selection->name}' settings have been saved.",
            null,
            MemoraFrontendUrls::selectionDetailPath($selection->uuid, $selection->project_uuid),
            ['coverPhoto' => $selection->cover_photo_url]
        );

        $this->activityLogService->log(
            'updated',
            $selection,
            "Updated selection phase '{$selection->name}'",
            [
                'phase_type' => 'selection',
                'project_uuid' => $selection->project_uuid,
                'selection_uuid' => $selection->uuid,
            ],
            $user
        );

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

        $user = Auth::user();
        if ($user) {
            $query->with(['starredByUsers' => function ($q) use ($user) {
                $q->where('user_uuid', $user->uuid);
            }]);
        }

        $selection = $query->firstOrFail();

        // Mark media as selected and update selection status in a transaction
        return DB::transaction(function () use ($selection, $id, $mediaIds, $completedByEmail, $user) {
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
                'auto_delete_date' => now()->addDays(30),
            ]);

            $selection->refresh();
            $selection->load(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }]);

            if ($user) {
                $selection->load(['starredByUsers' => function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid);
                }]);
            }

            // Notify the selection owner when a client completes the selection
            try {
                $actionUrl = MemoraFrontendUrls::selectionDetailPath($selection->uuid, $selection->project_uuid);
                $this->notificationService->create(
                    $selection->user_uuid,
                    'memora',
                    'selection_completed',
                    'Selection Completed',
                    "Selection '{$selection->name}' has been completed by a client.",
                    $completedByEmail
                        ? "Selection '{$selection->name}' has been completed by {$completedByEmail}."
                        : "Selection '{$selection->name}' has been completed.",
                    null,
                    $actionUrl,
                    ['coverPhoto' => $selection->cover_photo_url, 'selection_uuid' => $selection->uuid]
                );
            } catch (\Exception $e) {
                Log::error('Failed to send selection completion notification', [
                    'selection_uuid' => $selection->uuid,
                    'user_uuid' => $selection->user_uuid,
                    'error' => $e->getMessage(),
                ]);
            }

            // Log activity for selection completion
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                action: 'selection_completed',
                subject: $selection,
                description: "Selection '{$selection->name}' completed.",
                properties: [
                    'selection_uuid' => $selection->uuid,
                    'selection_name' => $selection->name,
                    'project_uuid' => $selection->project_uuid,
                    'completed_by_email' => $completedByEmail,
                    'media_count' => count($mediaIds),
                ],
                causer: $user ?? $selection->user
            );

            return new SelectionResource($selection);
        });
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
            ->with(['file'])
            ->orderBy('order');

        if ($setUuid) {
            $query->where('media_set_uuid', $setUuid);
        }

        return $query->get();
    }

    /**
     * Get selected filenames
     */
    public function getSelectedFilenames(string $id, ?string $setId = null): array
    {
        $query = MemoraMedia::query()
            ->whereHas('mediaSet', function ($q) use ($id, $setId) {
                $q->where('selection_uuid', $id);
                if ($setId) {
                    $q->where('uuid', $setId);
                }
            })
            ->where('is_selected', true)
            ->with('file')
            ->orderBy('order');

        $mediaItems = $query->get();

        $filenames = $mediaItems->map(function ($media) {
            return $media->file?->filename ?? null;
        })
            ->filter()
            ->values()
            ->toArray();

        return [
            'filenames' => $filenames,
            'count' => count($filenames),
        ];
    }

    /**
     * Reset selection limit
     */
    public function resetSelectionLimit(string $id): SelectionResource
    {
        $selection = MemoraSelection::query()->where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $id)
            ->firstOrFail();

        // Only allow reset if selection is completed
        if ($selection->status->value !== 'completed') {
            throw new \RuntimeException('Selection limit can only be reset for completed selections');
        }

        $selection->update([
            'reset_selection_limit_at' => now(),
        ]);

        return $this->find($id);
    }

    /**
     * Set cover photo from media thumbnail URL
     */
    public function setCoverPhotoFromMedia(string $selectionId, string $mediaUuid, ?array $focalPoint = null): SelectionResource
    {
        // Find the selection and verify ownership
        $selection = MemoraSelection::query()
            ->where('uuid', $selectionId)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        // Find the media and verify it belongs to this selection
        $media = MemoraMedia::query()
            ->where('uuid', $mediaUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->with('file')
            ->whereHas('mediaSet', function ($query) use ($selectionId) {
                $query->where('selection_uuid', $selectionId);
            })
            ->firstOrFail();

        // Cover URL: never original. Use thumb/medium only. Originals are for download only.
        $coverUrl = null;
        if ($media->file) {
            $file = $media->file;
            $fileType = $file->type?->value ?? $file->type;
            $metadata = $file->metadata;
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true);
            }
            $variants = is_array($metadata['variants'] ?? null) ? $metadata['variants'] : [];

            if ($fileType === 'video') {
                $coverUrl = $metadata['thumbnail'] ?? $variants['thumb'] ?? null;
            } else {
                $coverUrl = $variants['medium'] ?? $variants['thumb'] ?? null;
            }
        }

        if (! $coverUrl) {
            throw new \RuntimeException('Media does not have a valid non-original URL for cover (thumb/medium required)');
        }

        // Prepare update data
        $updateData = [
            'cover_photo_url' => $coverUrl,
        ];

        // Add focal point if provided
        if ($focalPoint !== null) {
            $updateData['cover_focal_point'] = $focalPoint;
        }

        // Update selection with cover photo URL and optional focal point
        // For videos, this will be the video URL; for images, it will be the thumbnail URL
        $selection->update($updateData);

        // Return updated selection
        return $this->find($selectionId);
    }

    /**
     * Toggle star status for a selection
     *
     * @param  string  $id  Selection UUID
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
     * Only deletes unselected media (is_selected = false), keeping selected media and selection intact
     *
     * @return array{unselected_media_deleted: int}
     */
    public function autoDeleteExpiredSelections(): array
    {
        $expiredSelections = MemoraSelection::query()
            ->whereNotNull('auto_delete_date')
            ->where('auto_delete_date', '<=', now())
            ->get();

        $unselectedMediaDeleted = 0;

        foreach ($expiredSelections as $selection) {
            try {
                // Get all unselected media in this selection (only where is_selected = false)
                $unselectedMedia = MemoraMedia::query()
                    ->whereHas('mediaSet', function ($query) use ($selection) {
                        $query->where('selection_uuid', $selection->uuid);
                    })
                    ->where('is_selected', false)
                    ->get();

                // Delete only the unselected media (not selected media)
                foreach ($unselectedMedia as $media) {
                    try {
                        $media->delete();
                        $unselectedMediaDeleted++;
                    } catch (\Exception $e) {
                        Log::error(
                            "Failed to auto-delete unselected media {$media->uuid}: ".$e->getMessage()
                        );
                    }
                }

                // Clear the auto_delete_date since we've processed the unselected media
                // This prevents it from being processed again
                // The selection remains intact regardless of remaining media
                $selection->update(['auto_delete_date' => null]);
            } catch (\Exception $e) {
                // Log error but continue with other selections
                Log::error(
                    "Failed to auto-delete expired selection {$selection->uuid}: ".$e->getMessage()
                );
            }
        }

        return [
            'unselected_media_deleted' => $unselectedMediaDeleted,
        ];
    }

    /**
     * Duplicate a selection with all settings, media sets, and media
     *
     * @param  string  $id  Selection UUID
     * @return SelectionResource The duplicated selection
     */
    public function duplicate(string $id): SelectionResource
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Load the original selection with all relationships
        $original = MemoraSelection::where('uuid', $id)
            ->where('user_uuid', $user->uuid)
            ->with([
                'mediaSets' => function ($query) {
                    $query->with(['media' => function ($q) {
                        $q->orderBy('order', 'asc');
                    }])->orderBy('order', 'asc');
                },
            ])
            ->firstOrFail();

        // Create the duplicated selection
        $duplicated = MemoraSelection::create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $original->project_uuid,
            'name' => $original->name.' (Copy)',
            'description' => $original->description,
            'status' => 'draft',
            'color' => $original->color,
            'password' => $original->password,
            'allowed_emails' => $original->allowed_emails,
            'selection_limit' => $original->selection_limit,
            'settings' => $original->settings,
            'auto_delete_enabled' => false, // Reset auto-delete
            'auto_delete_days' => null,
            'auto_delete_date' => null,
        ]);

        // Duplicate media sets and their media
        foreach ($original->mediaSets as $originalSet) {
            $newSet = MemoraMediaSet::create([
                'user_uuid' => $user->uuid,
                'selection_uuid' => $duplicated->uuid,
                'project_uuid' => $originalSet->project_uuid,
                'name' => $originalSet->name,
                'description' => $originalSet->description,
                'order' => $originalSet->order,
                'selection_limit' => $originalSet->selection_limit,
            ]);
            $newSet->refresh(); // Ensure UUID is loaded from database
            $newSetUuid = $newSet->uuid;

            // Duplicate media items
            foreach ($originalSet->media as $originalMedia) {
                MemoraMedia::create([
                    'user_uuid' => $user->uuid,
                    'media_set_uuid' => $newSetUuid,
                    'user_file_uuid' => $originalMedia->user_file_uuid,
                    'original_file_uuid' => $originalMedia->original_file_uuid,
                    'watermark_uuid' => $originalMedia->watermark_uuid,
                    'order' => $originalMedia->order,
                    'is_selected' => false, // Reset selection status
                    'is_private' => false, // Reset private status
                ]);
            }
        }

        $this->notificationService->create(
            $user->uuid,
            'memora',
            'selection_duplicated',
            'Selection Duplicated',
            "Selection '{$duplicated->name}' has been created from '{$original->name}'.",
            'Your duplicate is ready with the same media and settings.',
            null,
            MemoraFrontendUrls::selectionDetailPath($duplicated->uuid, $duplicated->project_uuid),
            ['coverPhoto' => $original->cover_photo_url ?? $duplicated->cover_photo_url]
        );

        return new SelectionResource($this->findModel($duplicated->uuid));
    }

    /**
     * Delete a selection and all its sets and media
     */
    public function delete(string $id): bool
    {
        $selection = $this->findModel($id);

        // Load media sets relationship if not already loaded
        if (! $selection->relationLoaded('mediaSets')) {
            $selection->load('mediaSets.media');
        }

        // Get all media sets for this selection
        $mediaSets = $selection->mediaSets;

        // Soft delete all media in all sets, then delete all sets, then delete selection in a transaction
        return DB::transaction(function () use ($mediaSets, $selection) {
            $user = Auth::user();
            $name = $selection->name;

            // Soft delete all media in all sets, then delete all sets
            foreach ($mediaSets as $set) {
                // Ensure media is loaded for this set
                if (! $set->relationLoaded('media')) {
                    $set->load('media');
                }

                // Soft delete all media in this set
                // Loop through each media item to ensure soft deletes work correctly
                foreach ($set->media as $media) {
                    $media->delete();
                }
                // Soft delete the set
                $set->delete();
            }

            // Soft delete the selection itself
            $deleted = $selection->delete();

            // Create notification if user is authenticated and deletion was successful
            if ($deleted && $user) {
                $this->notificationService->create(
                    $user->uuid,
                    'memora',
                    'selection_deleted',
                    'Selection Deleted',
                    "Selection '{$name}' has been deleted.",
                    "The selection '{$name}' has been permanently removed.",
                    null,
                    MemoraFrontendUrls::selectionListPath($selection->project_uuid)
                );

                $this->activityLogService->log(
                    'deleted',
                    null,
                    "Deleted selection phase '{$name}'",
                    [
                        'phase_type' => 'selection',
                        'selection_uuid' => $selection->uuid,
                        'project_uuid' => $selection->project_uuid,
                    ],
                    $user
                );
            }

            return $deleted;
        });
    }
}
