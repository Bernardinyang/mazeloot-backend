<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Resources\V1\ProofingResource;
use App\Services\Pagination\PaginationService;
use App\Services\Upload\UploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProofingService
{
    protected UploadService $uploadService;

    protected PaginationService $paginationService;

    public function __construct(UploadService $uploadService, PaginationService $paginationService)
    {
        $this->uploadService = $uploadService;
        $this->paginationService = $paginationService;
    }

    /**
     * Create a proofing phase (standalone or project-based)
     */
    public function create(array $data): MemoraProofing
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $projectUuid = $data['project_uuid'] ?? $data['projectUuid'] ?? null;
        $project = null;

        if ($projectUuid) {
            // Validate project exists
            $project = MemoraProject::findOrFail($projectUuid);
        }

        return MemoraProofing::create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $projectUuid,
            'name' => $data['name'] ?? 'Proofing',
            'description' => (isset($data['description']) && trim($data['description']) !== '') ? trim($data['description']) : null,
            'max_revisions' => $data['maxRevisions'] ?? 5,
            'status' => $data['status'] ?? 'draft',
            'color' => $data['color'] ?? $project?->color ?? '#F59E0B',
        ]);
    }

    /**
     * Create a proofing phase (project-based, for backward compatibility)
     */
    public function createForProject(string $projectId, array $data): MemoraProofing
    {
        $data['project_uuid'] = $projectId;

        return $this->create($data);
    }

    /**
     * Upload a revision
     * Note: Revisions can only be uploaded if media is ready for revision (approved closure request).
     */
    public function uploadRevision(?string $projectId, string $id, string $mediaId, int $revisionNumber, string $description, string $userFileUuid, array $completedTodos = []): MemoraMedia
    {
        $proofing = $this->find($projectId, $id);
        $originalMedia = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->findOrFail($mediaId);

        // Check if media is ready for revision
        if (! $originalMedia->is_ready_for_revision) {
            throw new \Exception('Media is not ready for revision. A closure request must be approved first.');
        }

        // Verify user_file_uuid exists and belongs to the authenticated user
        $userFile = \App\Models\UserFile::query()
            ->where('uuid', $userFileUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        // Get original media UUID (use original_media_uuid if exists, otherwise use media UUID)
        $originalMediaUuid = $originalMedia->original_media_uuid ?? $originalMedia->uuid;

        // Calculate revision number if not provided or validate it
        $calculatedRevisionNumber = $revisionNumber;
        if ($revisionNumber <= 0) {
            // Calculate next revision number
            $maxRevision = MemoraMedia::where(function ($query) use ($originalMediaUuid) {
                $query->where('original_media_uuid', $originalMediaUuid)
                    ->orWhere('uuid', $originalMediaUuid);
            })->max('revision_number') ?? 0;
            $calculatedRevisionNumber = $maxRevision + 1;
        }

        // Check if revision limit is exceeded
        $maxRevisions = $proofing->max_revisions ?? 5;
        if ($calculatedRevisionNumber > $maxRevisions) {
            throw new \Exception("Maximum revision limit ({$maxRevisions}) has been reached for this proofing. Cannot upload revision {$calculatedRevisionNumber}.");
        }

        // Get max order for the set
        $maxOrder = MemoraMedia::where('media_set_uuid', $originalMedia->media_set_uuid)
            ->max('order') ?? -1;

        // Get approved closure request todos to map completed todos
        $revisionTodos = [];
        if (! empty($completedTodos)) {
            $approvedClosureRequest = \App\Domains\Memora\Models\MemoraClosureRequest::where('media_uuid', $mediaId)
                ->where('status', 'approved')
                ->orderBy('approved_at', 'desc')
                ->first();

            if ($approvedClosureRequest && ! empty($approvedClosureRequest->todos)) {
                foreach ($approvedClosureRequest->todos as $index => $todo) {
                    $revisionTodos[] = [
                        'text' => $todo['text'] ?? $todo,
                        'completed' => in_array($index, $completedTodos),
                    ];
                }
            }
        }

        // Create new media record for revision and mark older revisions as revised in a transaction
        return DB::transaction(function () use ($originalMediaUuid, $calculatedRevisionNumber, $originalMedia, $userFile, $description, $revisionTodos, $maxOrder) {
            // Create new media record for revision
            $revisionMedia = MemoraMedia::create([
                'user_uuid' => Auth::user()->uuid,
                'media_set_uuid' => $originalMedia->media_set_uuid,
                'user_file_uuid' => $userFile->uuid,
                'original_media_uuid' => $originalMediaUuid,
                'revision_number' => $calculatedRevisionNumber,
                'revision_description' => $description,
                'revision_todos' => $revisionTodos,
                'order' => $maxOrder + 1,
                'is_completed' => false,
                'is_ready_for_revision' => false,
                'is_revised' => false,
            ]);

            // Mark all older revisions (including original) as revised
            MemoraMedia::where(function ($query) use ($originalMediaUuid) {
                $query->where('original_media_uuid', $originalMediaUuid)
                    ->orWhere('uuid', $originalMediaUuid);
            })
                ->where('uuid', '!=', $revisionMedia->uuid)
                ->update([
                    'is_revised' => true,
                    'is_ready_for_revision' => false,
                ]);

            $revisionMedia->load('file');

            return $revisionMedia;
        });
    }

    /**
     * Get a proofing phase (standalone or project-based)
     *
     * @param  string|null  $projectId  If provided, validates proofing belongs to that project. If null, finds any proofing by ID.
     */
    public function find(?string $projectId, string $id): MemoraProofing
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)->where('uuid', $id);

        if ($projectId) {
            // Validate project exists and proofing belongs to it
            MemoraProject::findOrFail($projectId);
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find proofing regardless of project association

        $proofing = $query->firstOrFail();

        // Load relationships for the resource
        $proofing->load(['mediaSets' => function ($query) {
            $query->withCount('media')->orderBy('order');
        }]);

        // Load starredByUsers for the current user only
        $proofing->load(['starredByUsers' => function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        }]);

        // Load counts through media sets (only active revisions)
        $mediaCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->count();
        $completedCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->where('is_completed', true)
            ->count();

        $proofing->setAttribute('media_count', $mediaCount);
        $proofing->setAttribute('completed_count', $completedCount);
        $proofing->setAttribute('pending_count', $mediaCount - $completedCount);

        return $proofing;
    }

    /**
     * Complete proofing (requires authentication)
     */
    public function complete(?string $projectId, string $id): MemoraProofing
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)
            ->where('uuid', $id);

        if ($projectId) {
            $query->where('project_uuid', $projectId);
        }

        $proofing = $query->firstOrFail();

        // Validate all media is completed (only active revisions)
        $mediaCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->count();

        $completedCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->where('is_completed', true)
            ->count();

        if ($mediaCount > 0 && $completedCount < $mediaCount) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['proofing' => ['Cannot complete proofing: not all media items are completed.']]
            );
        }

        // Update only database columns using update() to avoid computed attributes
        MemoraProofing::where('uuid', $id)->update([
            'status' => \App\Domains\Memora\Enums\ProofingStatusEnum::COMPLETED->value,
            'completed_at' => now(),
        ]);

        // Reload with relationships and recompute counts
        return $this->find($projectId, $id);
    }

    /**
     * Complete a proofing phase (standalone)
     */
    public function completeStandalone(string $id): MemoraProofing
    {
        return $this->complete(null, $id);
    }

    /**
     * Complete proofing (public/guest access - no authentication required)
     */
    public function completePublic(string $id): MemoraProofing
    {
        $proofing = MemoraProofing::where('uuid', $id)->firstOrFail();

        // Validate all media is completed (only active revisions)
        $mediaCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->count();

        $completedCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->where('is_completed', true)
            ->count();

        if ($mediaCount === 0) {
            throw new \RuntimeException('Cannot complete proofing with no media items');
        }

        if ($completedCount !== $mediaCount) {
            throw new \RuntimeException("Cannot complete proofing. Only {$completedCount} of {$mediaCount} media items are approved.");
        }

        // Update proofing status to completed
        MemoraProofing::where('uuid', $id)->update([
            'status' => \App\Domains\Memora\Enums\ProofingStatusEnum::COMPLETED->value,
            'completed_at' => now(),
        ]);

        // Reload with relationships and recompute counts (without authentication requirement)
        $proofing = MemoraProofing::where('uuid', $id)
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')
                    ->withCount(['media as approved_count' => function ($q) {
                        $q->where('is_completed', true);
                    }])
                    ->orderBy('order');
            }])
            ->firstOrFail();

        // Load counts through media sets (only active revisions)
        $mediaCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->count();
        $completedCount = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->where('is_revised', false)
            ->where('is_completed', true)
            ->count();

        $proofing->setAttribute('media_count', $mediaCount);
        $proofing->setAttribute('completed_count', $completedCount);
        $proofing->setAttribute('pending_count', $mediaCount - $completedCount);

        return $proofing;
    }

    /**
     * Update a proofing phase (standalone or project-based)
     */
    public function update(?string $projectId, string $id, array $data): MemoraProofing
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)->where('uuid', $id);

        if ($projectId) {
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find proofing regardless of project association

        $proofing = $query->firstOrFail();

        Log::info('Proofing update received data', [
            'proofing_id' => $id,
            'received_data_keys' => array_keys($data),
            'has_primaryEmail' => array_key_exists('primaryEmail', $data),
            'has_primary_email' => array_key_exists('primary_email', $data),
            'primaryEmail_value' => $data['primaryEmail'] ?? 'not set',
            'primary_email_value' => $data['primary_email'] ?? 'not set',
        ]);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $desc = $data['description'];
            if ($desc === null || $desc === '') {
                $updateData['description'] = null;
            } else {
                $updateData['description'] = trim((string) $desc);
            }
        }
        if (isset($data['maxRevisions'])) {
            $updateData['max_revisions'] = $data['maxRevisions'];
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

        // Handle allowedEmails (camelCase) or allowed_emails (snake_case)
        if (array_key_exists('allowedEmails', $data) || array_key_exists('allowed_emails', $data)) {
            $emails = $data['allowedEmails'] ?? $data['allowed_emails'] ?? [];
            // Ensure it's an array
            $emails = is_array($emails) ? $emails : [];

            Log::info('Processing allowed_emails', [
                'proofing_id' => $id,
                'raw_input' => $emails,
                'is_array' => is_array($emails),
            ]);

            // Filter and validate emails
            $validEmails = [];
            foreach ($emails as $email) {
                $trimmed = trim($email ?? '');
                if (empty($trimmed)) {
                    continue;
                }
                $lowercased = strtolower($trimmed);
                if (filter_var($lowercased, FILTER_VALIDATE_EMAIL)) {
                    $validEmails[] = $lowercased;
                }
            }

            // Remove duplicates and re-index
            $validEmails = array_values(array_unique($validEmails));

            // Always set as array, even if empty (don't set to null)
            $updateData['allowed_emails'] = $validEmails;

            Log::info('Updating proofing allowed_emails', [
                'proofing_id' => $id,
                'input_emails' => $emails,
                'valid_emails' => $validEmails,
                'update_data_keys' => array_keys($updateData),
            ]);
        }

        // Handle primaryEmail (camelCase) or primary_email (snake_case)
        if (array_key_exists('primaryEmail', $data) || array_key_exists('primary_email', $data)) {
            $primaryEmail = $data['primaryEmail'] ?? $data['primary_email'] ?? null;

            // Treat empty string as null
            if ($primaryEmail === '' || $primaryEmail === null) {
                $primaryEmail = null;
            }

            if ($primaryEmail !== null) {
                // Normalize primary email
                $primaryEmail = strtolower(trim($primaryEmail));

                // Validate email format
                if (! filter_var($primaryEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        ['primary_email' => ['The primary email must be a valid email address.']]
                    );
                }

                // Ensure primary email is in allowed_emails (normalize both for comparison)
                $allowedEmails = $updateData['allowed_emails'] ?? $proofing->allowed_emails ?? [];
                // Normalize allowed emails to lowercase for comparison
                $normalizedAllowedEmails = array_map(function ($email) {
                    return strtolower(trim($email ?? ''));
                }, $allowedEmails);

                if (! in_array($primaryEmail, $normalizedAllowedEmails)) {
                    // If primary email is not in allowed emails, add it
                    $allowedEmails[] = $primaryEmail;
                    $allowedEmails = array_values(array_unique($allowedEmails));
                    $updateData['allowed_emails'] = $allowedEmails;

                    Log::info('Primary email added to allowed_emails', [
                        'proofing_id' => $id,
                        'primary_email' => $primaryEmail,
                        'updated_allowed_emails' => $allowedEmails,
                    ]);
                }

                $updateData['primary_email'] = $primaryEmail;

                Log::info('Setting primary_email', [
                    'proofing_id' => $id,
                    'primary_email' => $primaryEmail,
                    'allowed_emails' => $allowedEmails,
                ]);
            } else {
                // If null is explicitly passed, remove primary email
                $updateData['primary_email'] = null;
                Log::info('Removing primary_email', [
                    'proofing_id' => $id,
                ]);
            }
        } else {
            // If allowed_emails is being updated but primary_email is not provided,
            // check if current primary_email is still in the new allowed_emails list
            if (isset($updateData['allowed_emails'])) {
                $currentPrimaryEmail = $proofing->primary_email;
                if ($currentPrimaryEmail) {
                    $normalizedCurrentPrimary = strtolower(trim($currentPrimaryEmail));
                    $normalizedAllowedEmails = array_map(function ($email) {
                        return strtolower(trim($email ?? ''));
                    }, $updateData['allowed_emails']);

                    if (! in_array($normalizedCurrentPrimary, $normalizedAllowedEmails)) {
                        // Current primary email is not in new allowed emails, remove it
                        $updateData['primary_email'] = null;
                        Log::info('Removing primary_email (not in allowed_emails)', [
                            'proofing_id' => $id,
                            'current_primary_email' => $currentPrimaryEmail,
                            'allowed_emails' => $updateData['allowed_emails'],
                        ]);
                    }
                }
            }
        }

        // Handle password update
        if (array_key_exists('password', $data)) {
            if (! empty($data['password'])) {
                $updateData['password'] = $data['password'];
            } else {
                $updateData['password'] = null;
            }
        }

        // Handle settings updates (typographyDesign and galleryAssist)
        $needsSettingsUpdate = false;
        // Always start with existing settings to preserve all existing values
        $settings = $proofing->settings ?? [];

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

        Log::info('Proofing update data before save', [
            'proofing_id' => $id,
            'update_data' => $updateData,
            'has_allowed_emails' => isset($updateData['allowed_emails']),
            'has_primary_email' => isset($updateData['primary_email']),
            'primary_email_value' => $updateData['primary_email'] ?? 'not set',
        ]);

        $proofing->update($updateData);

        $updated = $proofing->fresh();

        Log::info('Proofing updated', [
            'proofing_id' => $id,
            'allowed_emails_after_save' => $updated->allowed_emails,
            'primary_email_after_save' => $updated->primary_email,
        ]);

        return $updated;
    }

    /**
     * Update a proofing phase (project-based, for backward compatibility)
     */
    public function updateForProject(string $projectId, string $id, array $data): MemoraProofing
    {
        return $this->update($projectId, $id, $data);
    }

    /**
     * Update a proofing phase (standalone)
     */
    public function updateStandalone(string $id, array $data): MemoraProofing
    {
        return $this->update(null, $id, $data);
    }

    /**
     * Move media to collection
     * Moves media by updating their media_set_uuid to point to a project-level set
     */
    public function moveToCollection(string $projectId, string $id, array $mediaIds, string $collectionUuid): array
    {
        $proofing = $this->find($projectId, $id);

        // Verify collection exists
        $collection = \App\Domains\Memora\Models\MemoraCollection::where('project_uuid', $projectId)
            ->where('uuid', $collectionUuid)
            ->firstOrFail();

        // Get the first project-level media set (not tied to selection or proofing)
        // Collections use project-level sets
        $collectionSet = \App\Domains\Memora\Models\MemoraMediaSet::where('project_uuid', $projectId)
            ->whereNull('selection_uuid')
            ->whereNull('proof_uuid')
            ->orderBy('order')
            ->first();

        if (! $collectionSet) {
            // Create a default set for the collection
            $collectionSet = \App\Domains\Memora\Models\MemoraMediaSet::create([
                'user_uuid' => \Illuminate\Support\Facades\Auth::user()->uuid,
                'project_uuid' => $projectId,
                'name' => 'Default Set',
                'order' => 0,
            ]);
        }

        // Move media by updating their media_set_uuid
        $moved = MemoraMedia::whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->whereIn('uuid', $mediaIds)
            ->update([
                'media_set_uuid' => $collectionSet->uuid,
            ]);

        return [
            'movedCount' => $moved,
            'collectionId' => $collectionUuid,
        ];
    }

    /**
     * Get all proofing with optional search, sort, filter, and pagination parameters
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
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::query()
            ->where('user_uuid', $user->uuid)
            ->with(['project'])
            ->with(['mediaSets' => function ($query) {
                $query->withCount('media')->orderBy('order');
            }])
            ->with(['starredByUsers' => function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            }])
            // Add subqueries for media counts to avoid N+1 queries (only active revisions)
            ->addSelect([
                'media_count' => MemoraMedia::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.proof_uuid', 'memora_proofing.uuid')
                    ->where('memora_media.is_revised', false)
                    ->limit(1),
                'completed_count' => MemoraMedia::query()->selectRaw('COALESCE(COUNT(*), 0)')
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->whereColumn('memora_media_sets.proof_uuid', 'memora_proofing.uuid')
                    ->where('memora_media.is_revised', false)
                    ->where('memora_media.is_completed', true)
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

        // Filter by starred status (if proofing has starred relationship)
        // Note: This assumes proofing has a starred relationship similar to selections
        // If not implemented yet, this will be ignored
        if ($starred !== null) {
            // TODO: Implement starred relationship for proofing if needed
            // For now, we'll skip this filter
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
        foreach ($paginator->items() as $proofing) {
            $proofing->setAttribute('media_count', (int) ($proofing->media_count ?? 0));
            $proofing->setAttribute('completed_count', (int) ($proofing->completed_count ?? 0));
            $proofing->setAttribute('pending_count', $proofing->media_count - $proofing->completed_count);
            // Set set count from loaded relationship
            if ($proofing->relationLoaded('mediaSets')) {
                $proofing->setAttribute('set_count', $proofing->mediaSets->count());
            }
        }

        // Transform items to resources
        $data = ProofingResource::collection($paginator->items());

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

    /**
     * Get selected filenames for proofing
     */
    public function getSelectedFilenames(string $id, ?string $setId = null): array
    {
        $query = MemoraMedia::query()
            ->whereHas('mediaSet', function ($q) use ($id, $setId) {
                $q->where('proof_uuid', $id);
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
     * Duplicate a proofing with all settings, media sets, and media
     *
     * @param  string|null  $projectId  Project UUID if proofing is project-based
     * @param  string  $id  Proofing UUID
     * @return MemoraProofing The duplicated proofing
     */
    public function duplicate(?string $projectId, string $id): MemoraProofing
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('uuid', $id)->where('user_uuid', $user->uuid);

        if ($projectId) {
            $query->where('project_uuid', $projectId);
        }

        // Load the original proofing with all relationships
        $original = $query->with([
            'mediaSets' => function ($query) {
                $query->with(['media' => function ($q) {
                    $q->where('is_revised', false)->orderBy('order', 'asc');
                }])->orderBy('order', 'asc');
            },
        ])->firstOrFail();

        // Create the duplicated proofing
        $duplicated = MemoraProofing::create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $original->project_uuid,
            'name' => $original->name.' (Copy)',
            'description' => $original->description,
            'status' => 'draft',
            'color' => $original->color,
            'max_revisions' => $original->max_revisions,
            'current_revision' => 0, // Reset revision
            'password' => $original->password,
            'allowed_emails' => $original->allowed_emails,
            'primary_email' => $original->primary_email,
            'settings' => $original->settings,
        ]);

        // Duplicate media sets and their media
        foreach ($original->mediaSets as $originalSet) {
            $newSet = MemoraMediaSet::create([
                'user_uuid' => $user->uuid,
                'proof_uuid' => $duplicated->uuid,
                'project_uuid' => $originalSet->project_uuid,
                'name' => $originalSet->name,
                'description' => $originalSet->description,
                'order' => $originalSet->order,
                'selection_limit' => $originalSet->selection_limit,
            ]);
            $newSet->refresh(); // Ensure UUID is loaded from database
            $newSetUuid = $newSet->uuid;

            // Duplicate media items (only non-revised items)
            foreach ($originalSet->media as $originalMedia) {
                if ($originalMedia->is_revised) {
                    continue; // Skip revised items
                }
                MemoraMedia::create([
                    'user_uuid' => $user->uuid,
                    'media_set_uuid' => $newSetUuid,
                    'user_file_uuid' => $originalMedia->user_file_uuid,
                    'original_file_uuid' => $originalMedia->original_file_uuid,
                    'watermark_uuid' => $originalMedia->watermark_uuid,
                    'order' => $originalMedia->order,
                    'is_selected' => false, // Reset selection status
                    'is_completed' => false, // Reset completion status
                    'is_rejected' => false, // Reset rejection status
                    'is_revised' => false,
                    'is_private' => false, // Reset private status
                ]);
            }
        }

        return $duplicated->fresh()->load(['mediaSets' => function ($query) {
            $query->withCount('media')->orderBy('order', 'asc');
        }]);
    }

    /**
     * Delete a proofing phase and all its sets and media
     */
    public function delete(?string $projectId, string $id): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)->where('uuid', $id);

        if ($projectId) {
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find proofing regardless of project association

        $proofing = $query->firstOrFail();

        // Load media sets relationship if not already loaded
        if (! $proofing->relationLoaded('mediaSets')) {
            $proofing->load(['mediaSets.media.feedback.replies', 'mediaSets.media.file']);
        }

        // Get all media sets for this proofing
        $mediaSets = $proofing->mediaSets;

        // Soft delete all media in all sets, then delete all sets, then delete proofing in a transaction
        return DB::transaction(function () use ($mediaSets, $proofing) {
            // Soft delete all media in all sets, then delete all sets
            foreach ($mediaSets as $set) {
                // Ensure media is loaded for this set
                if (! $set->relationLoaded('media')) {
                    $set->load('media');
                }

                // Soft delete all media in this set
                foreach ($set->media as $media) {
                    $media->delete();
                }
                // Soft delete the set
                $set->delete();
            }

            // Soft delete the proofing itself
            return $proofing->delete();
        });
    }

    /**
     * Delete a proofing phase (standalone)
     */
    public function deleteStandalone(string $id): bool
    {
        return $this->delete(null, $id);
    }

    /**
     * Publish a proofing phase (toggle between draft and active)
     */
    public function publish(?string $projectId, string $id): MemoraProofing
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)->where('uuid', $id);

        if ($projectId) {
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find proofing regardless of project association

        $proofing = $query->firstOrFail();

        $newStatus = match ($proofing->status->value) {
            'draft' => 'active',
            'active' => 'draft',
            'completed' => 'active',
            default => 'active',
        };

        // Validate that at least one email is in allowed_emails before publishing to active
        if ($newStatus === 'active') {
            $allowedEmails = $proofing->allowed_emails ?? [];
            if (empty($allowedEmails) || ! is_array($allowedEmails) || count(array_filter($allowedEmails)) === 0) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['allowed_emails' => ['At least one email address must be added to "Allowed Emails" before publishing the proofing.']]
                );
            }
        }

        $proofing->update(['status' => $newStatus]);

        return $proofing->fresh();
    }

    /**
     * Publish a proofing phase (standalone)
     */
    public function publishStandalone(string $id): MemoraProofing
    {
        return $this->publish(null, $id);
    }

    /**
     * Toggle star status for a proofing phase
     */
    public function toggleStar(?string $projectId, string $id): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)->where('uuid', $id);

        if ($projectId) {
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find proofing regardless of project association

        $proofing = $query->firstOrFail();

        // Toggle the star relationship
        $user->starredProofing()->toggle($proofing->uuid);

        // Check if it's now starred
        $isStarred = $user->starredProofing()->where('proofing_uuid', $proofing->uuid)->exists();

        return [
            'starred' => $isStarred,
        ];
    }

    /**
     * Toggle star status for a proofing phase (standalone)
     */
    public function toggleStarStandalone(string $id): array
    {
        return $this->toggleStar(null, $id);
    }

    /**
     * Set cover photo from media
     */
    public function setCoverPhotoFromMedia(?string $projectId, string $proofingId, string $mediaUuid, ?array $focalPoint = null): MemoraProofing
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)->where('uuid', $proofingId);

        // Only filter by project_uuid if projectId is explicitly provided
        if ($projectId !== null && $projectId !== '') {
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find proofing regardless of project association

        $proofing = $query->firstOrFail();

        // Find the media and verify it belongs to this proofing
        // First, find the media with its relationships
        $media = MemoraMedia::query()
            ->where('uuid', $mediaUuid)
            ->with(['file', 'mediaSet'])
            ->first();

        if (! $media) {
            Log::error('Media not found', [
                'media_uuid' => $mediaUuid,
                'proofing_id' => $proofingId,
                'user_uuid' => $user->uuid,
            ]);
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Media not found');
        }

        // Verify the mediaSet exists and belongs to this proofing
        if (! $media->media_set_uuid) {
            Log::error('Media has no media_set_uuid', [
                'media_uuid' => $mediaUuid,
                'media_set_uuid' => $media->media_set_uuid,
            ]);
            throw new \RuntimeException('Media does not have an associated set');
        }

        // Verify media belongs to this proofing
        $mediaSet = $media->mediaSet;
        if (! $mediaSet || $mediaSet->proof_uuid !== $proofingId) {
            throw new \Exception('Media does not belong to this proofing');
        }

        // Load the mediaSet relationship if not loaded, including soft-deleted
        if (! $media->relationLoaded('mediaSet') || ! $media->mediaSet) {
            $mediaSet = MemoraMediaSet::withTrashed()
                ->where('uuid', $media->media_set_uuid)
                ->first();

            if (! $mediaSet) {
                Log::error('MediaSet not found', [
                    'media_uuid' => $mediaUuid,
                    'media_set_uuid' => $media->media_set_uuid,
                ]);
                throw new \RuntimeException('Media set not found');
            }

            $media->setRelation('mediaSet', $mediaSet);
        }

        // Verify the mediaSet belongs to this proofing
        if ($media->mediaSet->proof_uuid !== $proofingId) {
            Log::error('Media set does not belong to proofing', [
                'media_uuid' => $mediaUuid,
                'media_set_uuid' => $media->media_set_uuid,
                'media_set_proof_uuid' => $media->mediaSet->proof_uuid,
                'expected_proof_uuid' => $proofingId,
            ]);
            throw new \RuntimeException('Media does not belong to this proofing');
        }

        // Get cover URL from the media's file
        $coverUrl = null;

        // Try to load file relationship if not loaded and media has a user_file_uuid
        if (! $media->relationLoaded('file') && $media->user_file_uuid) {
            try {
                $media->load('file');
            } catch (\Exception $e) {
                Log::warning('Failed to load file relationship for media', [
                    'media_uuid' => $mediaUuid,
                    'user_file_uuid' => $media->user_file_uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($media->file) {
            $file = $media->file;
            $fileType = $file->type?->value ?? $file->type;

            if ($fileType === 'video') {
                $coverUrl = $file->url ?? null;
            } else {
                // Check metadata for variants (metadata is cast as array, but handle null case)
                $metadata = $file->metadata;

                // Handle case where metadata might be stored as JSON string (shouldn't happen with cast, but be safe)
                if (is_string($metadata)) {
                    $metadata = json_decode($metadata, true);
                }

                // Priority: original variant > large variant > file URL (use best quality)
                if ($metadata && is_array($metadata) && isset($metadata['variants']) && is_array($metadata['variants'])) {
                    if (isset($metadata['variants']['original'])) {
                        $coverUrl = $metadata['variants']['original'];
                    } elseif (isset($metadata['variants']['large'])) {
                        $coverUrl = $metadata['variants']['large'];
                    } else {
                        $coverUrl = $file->url ?? null;
                    }
                } else {
                    // Fallback to file URL (which should be the original)
                    $coverUrl = $file->url ?? null;
                }
            }
        }

        if (! $coverUrl) {
            $fileUrl = $media->file ? ($media->file->url ?? 'none') : 'no file relationship';
            Log::error('Failed to get cover URL for media', [
                'media_uuid' => $mediaUuid,
                'proofing_id' => $proofingId,
                'has_file' => $media->file ? 'yes' : 'no',
                'file_url' => $fileUrl,
                'file_metadata' => $media->file ? ($media->file->metadata ?? 'none') : 'none',
            ]);
            throw new \RuntimeException('Media does not have a valid URL for cover photo');
        }

        $updateData = [
            'cover_photo_url' => $coverUrl,
        ];

        if ($focalPoint !== null) {
            // Ensure focal point is properly formatted as JSON
            if (is_array($focalPoint)) {
                $updateData['cover_focal_point'] = $focalPoint;
            } else {
                // Try to decode if it's a JSON string
                $decoded = json_decode($focalPoint, true);
                $updateData['cover_focal_point'] = $decoded !== null ? $decoded : $focalPoint;
            }
        }

        try {
            $proofing->update($updateData);
        } catch (\Exception $e) {
            Log::error('Failed to update proofing cover photo', [
                'proofing_id' => $proofingId,
                'media_uuid' => $mediaUuid,
                'cover_url' => $coverUrl,
                'focal_point' => $focalPoint,
                'update_data' => $updateData,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $proofing->fresh();
    }

    /**
     * Set cover photo from media (standalone)
     */
    public function setCoverPhotoFromMediaStandalone(string $proofingId, string $mediaUuid, ?array $focalPoint = null): MemoraProofing
    {
        return $this->setCoverPhotoFromMedia(null, $proofingId, $mediaUuid, $focalPoint);
    }

    /**
     * Recover deleted media
     */
    public function recover(?string $projectId, string $id, array $mediaIds): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = MemoraProofing::where('user_uuid', $user->uuid)->where('uuid', $id);

        if ($projectId) {
            $query->where('project_uuid', $projectId);
        }
        // If no projectId provided, find proofing regardless of project association

        $proofing = $query->firstOrFail();

        $recovered = MemoraMedia::query()->whereHas('mediaSet', function ($query) use ($id) {
            $query->where('proof_uuid', $id);
        })
            ->whereIn('uuid', $mediaIds)
            ->withTrashed()
            ->restore();

        return [
            'recoveredCount' => count($mediaIds),
        ];
    }

    /**
     * Recover deleted media (standalone)
     */
    public function recoverStandalone(string $id, array $mediaIds): array
    {
        return $this->recover(null, $id, $mediaIds);
    }
}
