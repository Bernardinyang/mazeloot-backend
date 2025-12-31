<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaFeedback;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraSelection;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MediaService
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get phase media
     */
    public function getPhaseMedia(string $phaseType, string $phaseId, ?string $setUuid = null)
    {
        $query = MemoraMedia::where('phase', $phaseType)
            ->where('phase_id', $phaseId)
            ->with(['feedback.replies', 'file'])
            ->orderBy('order');

        if ($setUuid) {
            $query->where('set_id', $setUuid);
        }

        return $query->get();
    }

    /**
     * Move media between phases
     */
    public function moveBetweenPhases(array $mediaIds, string $fromPhase, string $fromPhaseId, string $toPhase, string $toPhaseId): array
    {
        $moved = MemoraMedia::whereIn('id', $mediaIds)
            ->where('phase', $fromPhase)
            ->where('phase_id', $fromPhaseId)
            ->update([
                'phase' => $toPhase,
                'phase_id' => $toPhaseId,
            ]);

        $media = MemoraMedia::whereIn('id', $mediaIds)->get();

        return [
            'movedCount' => $moved,
            'media' => $media,
        ];
    }

    /**
     * Generate low-res copy (queued job for image processing)
     */
    public function generateLowResCopy(string $id): MemoraMedia
    {
        $media = MemoraMedia::findOrFail($id);

        // Dispatch job to queue for async processing
        \App\Domains\Memora\Jobs\GenerateLowResCopyJob::dispatch($id);

        return $media;
    }

    /**
     * Process image (thumbnails, low-res copies, EXIF extraction).
     * Called by ProcessImageJob.
     */
    public function processImage(string $mediaId, array $options = []): void
    {
        $media = MemoraMedia::find($mediaId);

        if (! $media) {
            \Illuminate\Support\Facades\Log::warning("MemoraMedia not found for image processing: {$mediaId}");

            return;
        }

        try {
            // Generate thumbnail if needed
            if ($options['generateThumbnail'] ?? true) {
                $this->generateThumbnail($media);
            }

            // Generate low-res copy if needed
            if ($options['generateLowRes'] ?? false) {
                $this->processLowResCopy($mediaId);
            }

            // Extract EXIF data if needed
            if ($options['extractExif'] ?? false) {
                $this->extractExifData($media);
            }

            \Illuminate\Support\Facades\Log::info("Image processing completed for media: {$mediaId}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to process image for media {$mediaId}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate thumbnail for the media.
     */
    protected function generateThumbnail(MemoraMedia $media): void
    {
        // TODO: Implement thumbnail generation
        // This would resize the image to a standard thumbnail size (e.g., 300x300)
        // and upload it, then update the media record

        \Illuminate\Support\Facades\Log::info("Thumbnail generation placeholder for media: {$media->id}");
    }

    /**
     * Process low-res copy generation (called by job).
     */
    public function processLowResCopy(string $mediaId): void
    {
        $media = MemoraMedia::find($mediaId);

        if (! $media) {
            \Illuminate\Support\Facades\Log::warning("MemoraMedia not found for low-res copy generation: {$mediaId}");

            return;
        }

        try {
            // TODO: Implement actual image processing logic
            // This would:
            // 1. Download the original image
            // 2. Resize/compress it to low resolution
            // 3. Upload the processed image
            // 4. Update the media record with the low-res URL

            // Placeholder implementation
            $lowResUrl = ($media->file->url ?? '').'?lowres=true';

            $media->update([
                'low_res_copy_url' => $lowResUrl,
            ]);

            \Illuminate\Support\Facades\Log::info("Low-res copy generated for media: {$mediaId}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate low-res copy for media {$mediaId}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract EXIF data from the image.
     */
    protected function extractExifData(MemoraMedia $media): void
    {
        // TODO: Extract EXIF data (camera info, GPS, etc.) and store in properties
        \Illuminate\Support\Facades\Log::info("EXIF extraction placeholder for media: {$media->id}");
    }

    /**
     * Mark media as selected
     */
    public function markSelected(string $id, bool $isSelected): MemoraMedia
    {
        $media = MemoraMedia::findOrFail($id);

        // Load media set and selection relationships
        $media->load('mediaSet.selection');
        $set = $media->mediaSet;
        $selection = $set?->selection;

        // If marking as selected, validate against selection limits
        if ($isSelected && $selection) {
            $selectionLimitService = app(\App\Domains\Memora\Services\SelectionLimitService::class);
            $setId = $set->uuid;
            $selectionId = $selection->uuid;

            // Get current selected count for this set
            $currentCount = \App\Domains\Memora\Models\MemoraMedia::query()
                ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                ->where('memora_media_sets.uuid', $setId)
                ->where('memora_media.is_selected', true)
                ->whereNull('memora_media.deleted_at')
                ->count();

            // Check if selection is allowed
            if (! $selectionLimitService->checkSelectionLimit($selectionId, $setId, $currentCount)) {
                throw new \RuntimeException('Selection limit reached. Cannot select more items.');
            }
        }

        $media->update([
            'is_selected' => $isSelected,
            'selected_at' => $isSelected ? now() : null,
        ]);

        return $media->fresh();
    }

    /**
     * Get media revisions
     */
    public function getRevisions(string $id): array
    {
        $media = MemoraMedia::query()->findOrFail($id);

        // Determine the original media UUID
        $originalUuid = $media->original_media_uuid ?? $media->uuid;

        // Find all revisions (including the original) for this media
        $revisions = MemoraMedia::where(function ($query) use ($originalUuid) {
            $query->where('original_media_uuid', $originalUuid)
                ->orWhere('uuid', $originalUuid);
        })
            ->with([
                'feedback' => function ($query) {
                    $query->whereNull('parent_uuid')->orderBy('created_at', 'asc')
                        ->with(['replies' => function ($q) {
                            $this->loadRecursiveReplies($q, 0, 20);
                        }]);
                },
                'file',
            ])
            ->orderBy('revision_number', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return \App\Domains\Memora\Resources\V1\MediaResource::collection($revisions)->resolve();
    }

    /**
     * Mark media as completed
     */
    public function markCompleted(string $id, bool $isCompleted, ?string $userId = null): MemoraMedia
    {
        $media = MemoraMedia::findOrFail($id);

        // If userId is provided, verify user owns the proofing
        if ($userId !== null) {
            $mediaSet = $media->mediaSet;
            if ($mediaSet) {
                $proofing = $mediaSet->proofing;
                if ($proofing && $proofing->user_uuid !== $userId) {
                    throw new \Exception('Unauthorized: You do not own this proofing');
                }
            }
        }

        $media->update([
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
        ]);

        return $media->fresh();
    }

    public function markRejected(string $id, bool $isRejected, ?string $userId = null): MemoraMedia
    {
        $media = MemoraMedia::findOrFail($id);

        // If userId is provided, verify user owns the proofing
        if ($userId !== null) {
            $mediaSet = $media->mediaSet;
            if ($mediaSet) {
                $proofing = $mediaSet->proofing;
                if ($proofing && $proofing->user_uuid !== $userId) {
                    throw new \Exception('Unauthorized: You do not own this proofing');
                }
            }
        }

        $media->update([
            'is_rejected' => $isRejected,
            'rejected_at' => $isRejected ? now() : null,
        ]);

        return $media->fresh();
    }

    /**
     * Add feedback to media
     */
    public function addFeedback(string $mediaId, array $data): MemoraMediaFeedback
    {
        // Check if media exists and is not soft-deleted
        $media = MemoraMedia::where('uuid', $mediaId)->first();

        if (! $media) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "No query results for model [App\\Domains\\Memora\\Models\\MemoraMedia] {$mediaId}"
            );
        }

        // Block comments/feedback if media is already approved/completed
        if ($media->is_completed) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot add comments or feedback to approved media',
                    'error' => 'MEDIA_APPROVED',
                ], 403)
            );
        }

        // Block comments/feedback if media is rejected
        if ($media->is_rejected) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot add comments or feedback to rejected media',
                    'error' => 'MEDIA_REJECTED',
                ], 403)
            );
        }

        // Block comments/feedback if there's a pending closure request
        $pendingClosureRequest = \App\Domains\Memora\Models\MemoraClosureRequest::where('media_uuid', $mediaId)
            ->where('status', 'pending')
            ->exists();

        if ($pendingClosureRequest) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot add comments or feedback while a closure request is pending',
                    'error' => 'CLOSURE_REQUEST_PENDING',
                ], 403)
            );
        }

        // Handle created_by - it can be a string (email) or an object
        // For authenticated users, use their email if createdBy is not provided
        $createdBy = null;
        if (isset($data['createdBy']) && $data['createdBy'] !== null && $data['createdBy'] !== '') {
            if (is_string($data['createdBy'])) {
                // If it's a string (email), convert to JSON object with formatted name
                $email = $data['createdBy'];
                $formattedName = $this->formatNameFromEmail($email);
                $createdBy = json_encode([
                    'email' => $email,
                    'name' => $formattedName,
                ]);
            } elseif (is_array($data['createdBy'])) {
                // If it's already an array, ensure name is formatted if it's an email
                $createdByArray = $data['createdBy'];
                if (isset($createdByArray['email']) && (! isset($createdByArray['name']) || $createdByArray['name'] === $createdByArray['email'])) {
                    $createdByArray['name'] = $this->formatNameFromEmail($createdByArray['email']);
                }
                $createdBy = json_encode($createdByArray);
            } else {
                // If it's already JSON encoded, use as is
                $createdBy = $data['createdBy'];
            }
        } elseif (Auth::check()) {
            // If no createdBy provided but user is authenticated, use authenticated user's email
            $user = Auth::user();
            $email = $user->email;
            $formattedName = $this->formatNameFromEmail($email);
            $createdBy = json_encode([
                'email' => $email,
                'name' => $formattedName,
            ]);
        }

        // Handle mentions - ensure it's a JSON array
        $mentions = null;
        if (isset($data['mentions']) && is_array($data['mentions']) && count($data['mentions']) > 0) {
            // Validate and sanitize email addresses
            $mentions = array_filter(array_map(function ($email) {
                $email = trim($email);

                return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
            }, $data['mentions']));

            // Remove duplicates and re-index array
            $mentions = array_values(array_unique($mentions));

            // If no valid emails, set to null
            if (empty($mentions)) {
                $mentions = null;
            }
        }

        $feedback = MemoraMediaFeedback::create([
            'media_uuid' => $mediaId,
            'parent_uuid' => $data['parentId'] ?? null,
            'timestamp' => $data['timestamp'] ?? null,
            'mentions' => $mentions ? json_encode($mentions) : null,
            'type' => $data['type'],
            'content' => $data['content'],
            'created_by' => $createdBy,
        ]);

        // Dispatch event for real-time updates
        \App\Domains\Memora\Events\MediaFeedbackCreated::dispatch($feedback);

        return $feedback;
    }

    /**
     * Update feedback
     */
    public function updateFeedback(string $feedbackId, array $data): MemoraMediaFeedback
    {
        $feedback = MemoraMediaFeedback::findOrFail($feedbackId);
        $media = $feedback->media;

        // Block updates if media is already approved/completed
        if ($media->is_completed) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot update comments or feedback on approved media',
                    'error' => 'MEDIA_APPROVED',
                ], 403)
            );
        }

        // Block updates if media is rejected
        if ($media->is_rejected) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot update comments or feedback on rejected media',
                    'error' => 'MEDIA_REJECTED',
                ], 403)
            );
        }

        // Block updates if there's a pending closure request
        $pendingClosureRequest = \App\Domains\Memora\Models\MemoraClosureRequest::where('media_uuid', $media->uuid)
            ->where('status', 'pending')
            ->exists();

        if ($pendingClosureRequest) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot update comments or feedback while a closure request is pending',
                    'error' => 'CLOSURE_REQUEST_PENDING',
                ], 403)
            );
        }

        // Check if comment is within 2 minutes
        $createdAt = $feedback->created_at;
        $now = now();
        $diffMinutes = $now->diffInMinutes($createdAt);

        if ($diffMinutes > 2) {
            throw new \Exception('Comment can only be edited within 2 minutes of creation');
        }

        $feedback->update([
            'content' => $data['content'],
        ]);

        // Dispatch event for real-time updates
        \App\Domains\Memora\Events\MediaFeedbackUpdated::dispatch($feedback);

        return $feedback->fresh();
    }

    /**
     * Delete feedback
     */
    public function deleteFeedback(string $feedbackId): bool
    {
        $feedback = MemoraMediaFeedback::findOrFail($feedbackId);
        $media = $feedback->media;

        // Block deletes if media is already approved/completed
        if ($media->is_completed) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot delete comments or feedback on approved media',
                    'error' => 'MEDIA_APPROVED',
                ], 403)
            );
        }

        // Block deletes if media is rejected
        if ($media->is_rejected) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot delete comments or feedback on rejected media',
                    'error' => 'MEDIA_REJECTED',
                ], 403)
            );
        }

        // Block deletes if there's a pending closure request
        $pendingClosureRequest = \App\Domains\Memora\Models\MemoraClosureRequest::where('media_uuid', $media->uuid)
            ->where('status', 'pending')
            ->exists();

        if ($pendingClosureRequest) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Cannot delete comments or feedback while a closure request is pending',
                    'error' => 'CLOSURE_REQUEST_PENDING',
                ], 403)
            );
        }

        // Check if comment has replies
        if ($feedback->replies()->count() > 0) {
            throw new \Exception('Cannot delete comment with replies');
        }

        // Check if comment is within 2 minutes
        $createdAt = $feedback->created_at;
        $now = now();
        $diffMinutes = $now->diffInMinutes($createdAt);

        if ($diffMinutes > 2) {
            throw new \Exception('Comment can only be deleted within 2 minutes of creation');
        }

        return $feedback->delete();
    }

    /**
     * Format a display name from an email address
     * Example: "bernardinyang.bci@gmail.com" -> "Bernardinyang Bci"
     */
    protected function formatNameFromEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        // Extract the part before @
        $localPart = explode('@', $email)[0];

        // Replace dots and underscores with spaces
        $formatted = str_replace(['.', '_'], ' ', $localPart);

        // Capitalize words
        $words = explode(' ', $formatted);
        $capitalizedWords = array_map(function ($word) {
            return ucfirst(strtolower($word));
        }, $words);

        return implode(' ', $capitalizedWords) ?: $email;
    }

    /**
     * Get media for a specific set with optional sorting
     *
     * @param  string  $setUuid  The media set UUID
     * @param  string|null  $sortBy  Sort field and direction (e.g., 'uploaded-desc', 'name-asc', 'date-taken-desc')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    /**
     * Recursively load replies for a feedback query (helper method)
     * This creates a closure that loads nested replies up to maxDepth levels
     */
    private function loadRecursiveReplies($query, int $depth = 0, int $maxDepth = 20): void
    {
        if ($depth >= $maxDepth) {
            return; // Prevent infinite recursion
        }

        $query->orderBy('created_at', 'asc');

        // Load nested replies recursively
        $query->with(['replies' => function ($q) use ($depth, $maxDepth) {
            $this->loadRecursiveReplies($q, $depth + 1, $maxDepth);
        }]);
    }

    public function getSetMedia(string $setUuid, ?string $sortBy = null, ?int $page = null, ?int $perPage = null)
    {
        // Load feedback with recursive replies (up to 20 levels deep)
        $query = MemoraMedia::where('media_set_uuid', $setUuid)
            ->with([
                'feedback' => function ($query) {
                    $query->whereNull('parent_uuid')->orderBy('created_at', 'asc')
                        ->with(['replies' => function ($q) {
                            $this->loadRecursiveReplies($q, 0, 20);
                        }]);
                },
                'file',
            ]);

        // Only load starredByUsers if user is authenticated
        if (Auth::check()) {
            $query->with(['starredByUsers' => function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }]);
        }

        // Apply sorting
        if ($sortBy) {
            $this->applyMediaSorting($query, $sortBy);
        } else {
            // Default sort: order asc
            $query->orderBy('order');
        }

        // If pagination is requested, use pagination service
        if ($page !== null && $perPage !== null) {
            $paginationService = app(\App\Services\Pagination\PaginationService::class);
            $perPage = max(1, min(100, $perPage)); // Limit between 1 and 100
            $paginator = $paginationService->paginate($query, $perPage, $page);

            // If we used a join for sorting, reload relationships to ensure they're available
            if ($sortBy && str_starts_with($sortBy, 'name-')) {
                $relationships = ['feedback.replies', 'file'];
                if (Auth::check()) {
                    $relationships[] = 'starredByUsers';
                }
                $paginator->getCollection()->load($relationships);
            }

            // Transform items to resources
            $data = \App\Domains\Memora\Resources\V1\MediaResource::collection($paginator->items());

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

        // Non-paginated response (backward compatibility)
        $media = $query->get();

        // If we used a join for sorting, reload relationships to ensure they're available
        // This is necessary because joins can interfere with eager loading
        if ($sortBy && str_starts_with($sortBy, 'name-')) {
            $relationships = ['feedback.replies', 'file'];
            if (Auth::check()) {
                $relationships[] = 'starredByUsers';
            }
            $media->load($relationships);
        }

        return $media;
    }

    /**
     * Apply sorting to media query based on sortBy parameter
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $sortBy  Format: 'field-direction' (e.g., 'uploaded-desc', 'name-asc')
     */
    protected function applyMediaSorting($query, string $sortBy): void
    {
        $parts = explode('-', $sortBy);
        $field = $parts[0] ?? 'order';
        $direction = strtoupper($parts[1] ?? 'asc');

        // Validate direction
        if (! in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        // Handle special cases
        if ($sortBy === 'random') {
            $query->inRandomOrder();

            return;
        }

        // Map frontend sort values to database fields
        $fieldMap = [
            'uploaded' => 'created_at',
            'name' => 'filename', // Will join with user_file table
            'date-taken' => 'created_at', // TODO: Add date_taken field if available
            'order' => 'order',
        ];

        $dbField = $fieldMap[$field] ?? 'order';

        // For name sorting, join with user_file table to access filename
        if ($field === 'name') {
            $query->leftJoin('user_files', 'memora_media.user_file_uuid', '=', 'user_files.uuid')
                ->orderBy('user_files.filename', $direction)
                ->select('memora_media.*'); // Ensure we only select media columns
        } elseif ($field === 'uploaded') {
            // For uploaded sorting, use created_at
            $query->orderBy('memora_media.created_at', $direction);
        } elseif ($field === 'date-taken') {
            // For date-taken sorting, use created_at (TODO: use actual date_taken field if available)
            $query->orderBy('memora_media.created_at', $direction);
        } else {
            $query->orderBy('memora_media.'.$dbField, $direction);
        }
    }

    /**
     * Create media from user_file_uuid for a set
     * Uses user_file_uuid from the memora_media migration to store media
     * All metadata is retrieved from the UserFile relationship
     */
    public function createFromUploadUrlForSet(string $setUuid, array $data): MemoraMedia
    {
        $set = MemoraMediaSet::query()->findOrFail($setUuid);

        // Check if phase is completed
        if ($this->isPhaseCompleted($set)) {
            throw new \RuntimeException('Cannot upload media to a completed phase');
        }

        // Verify user_file_uuid exists and belongs to the authenticated user
        $userFile = \App\Models\UserFile::query()
            ->where('uuid', $data['user_file_uuid'])
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        // Get the maximum order for media in this set
        $maxOrder = MemoraMedia::query()->where('media_set_uuid', $setUuid)
            ->max('order') ?? -1;

        // Create media using only user_file_uuid - all other data comes from UserFile relationship
        // Newly uploaded media is tagged as draft (is_completed = false) until approved
        $media = MemoraMedia::query()->create([
            'user_uuid' => Auth::user()->uuid,
            'media_set_uuid' => $setUuid,
            'user_file_uuid' => $data['user_file_uuid'],
            'order' => $maxOrder + 1,
            'is_completed' => false,
        ]);

        // Load the file relationship for the response
        $media->load('file');

        return $media;
    }

    /**
     * Check if a phase (proofing or selection) is completed
     */
    protected function isPhaseCompleted(MemoraMediaSet $mediaSet): bool
    {
        if ($mediaSet->proof_uuid) {
            $proofing = MemoraProofing::where('uuid', $mediaSet->proof_uuid)->first();
            return $proofing && $proofing->status->value === 'completed';
        }

        if ($mediaSet->selection_uuid) {
            $selection = MemoraSelection::where('uuid', $mediaSet->selection_uuid)->first();
            return $selection && $selection->status->value === 'completed';
        }

        return false;
    }

    /**
     * Delete media from a set
     */
    public function delete(string $mediaId, ?string $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $media = MemoraMedia::where('uuid', $mediaId)->firstOrFail();

        // Verify user owns the proofing that contains this media
        $mediaSet = $media->mediaSet;
        if ($mediaSet) {
            $proofing = $mediaSet->proofing;
            if ($proofing && $proofing->user_uuid !== $userId) {
                throw new \Exception('Unauthorized: You do not own this proofing');
            }

            // Check if phase is completed
            if ($this->isPhaseCompleted($mediaSet)) {
                throw new \RuntimeException('Cannot delete media from a completed phase');
            }
        } else {
            // Fallback: verify user owns the media directly
            if ($media->user_uuid !== $userId) {
                throw new \Exception('Unauthorized: You do not own this media');
            }
        }

        // Delete the media record
        // Note: We don't delete the user_file record as it may be used elsewhere
        // The user_file will remain in the database for potential recovery
        return $media->delete();
    }

    /**
     * Move media items to a different set
     *
     * @param  array  $mediaUuids  Array of media UUIDs to move
     * @param  string  $targetSetUuid  Target set UUID
     * @return int Number of media items moved
     */
    public function moveMediaToSet(array $mediaUuids, string $targetSetUuid, ?string $userId = null): int
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify target set exists and user owns the proofing
        $targetSet = MemoraMediaSet::query()
            ->where('uuid', $targetSetUuid)
            ->firstOrFail();

        $targetProofing = $targetSet->proofing;
        if ($targetProofing && $targetProofing->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own the target proofing');
        }

        // Check if target phase is completed
        if ($this->isPhaseCompleted($targetSet)) {
            throw new \RuntimeException('Cannot move media to a completed phase');
        }

        // Verify all media items exist and user owns the proofing that contains them
        $mediaItems = MemoraMedia::whereIn('uuid', $mediaUuids)->get();

        if ($mediaItems->count() !== count($mediaUuids)) {
            throw new \RuntimeException('One or more media items not found');
        }

        foreach ($mediaItems as $media) {
            $mediaSet = $media->mediaSet;
            if ($mediaSet) {
                $proofing = $mediaSet->proofing;
                if ($proofing && $proofing->user_uuid !== $userId) {
                    throw new \Exception('Unauthorized: You do not own the proofing containing this media');
                }

                // Check if source phase is completed
                if ($this->isPhaseCompleted($mediaSet)) {
                    throw new \RuntimeException('Cannot move media from a completed phase');
                }
            } else {
                // Fallback: verify user owns the media directly
                if ($media->user_uuid !== $userId) {
                    throw new \Exception('Unauthorized: You do not own this media');
                }
            }
        }

        // Validate: Prevent moving to the same set
        $sourceSetUuids = $mediaItems->pluck('media_set_uuid')->unique();
        if ($sourceSetUuids->contains($targetSetUuid)) {
            throw new \RuntimeException('Cannot move media to the same set it already belongs to');
        }

        // Get the maximum order for media in the target set
        $maxOrder = MemoraMedia::where('media_set_uuid', $targetSetUuid)
            ->max('order') ?? -1;

        // Update media_set_uuid for each media item and set order in a transaction
        return DB::transaction(function () use ($mediaItems, $targetSetUuid, $maxOrder) {
            $movedCount = 0;
            foreach ($mediaItems as $index => $media) {
                $media->update([
                    'media_set_uuid' => $targetSetUuid,
                    'order' => $maxOrder + 1 + $index,
                ]);
                $movedCount++;
            }

            return $movedCount;
        });
    }

    /**
     * Copy media items to a different set
     * Creates new media entries pointing to the same user_file_uuid
     *
     * @param  array  $mediaUuids  Array of media UUIDs to copy
     * @param  string  $targetSetUuid  Target set UUID
     * @return array Array of newly created media items
     */
    public function copyMediaToSet(array $mediaUuids, string $targetSetUuid, ?string $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Verify target set exists and user owns the proofing
        $targetSet = MemoraMediaSet::query()
            ->where('uuid', $targetSetUuid)
            ->firstOrFail();

        $targetProofing = $targetSet->proofing;
        if ($targetProofing && $targetProofing->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own the target proofing');
        }

        // Check if target phase is completed
        if ($this->isPhaseCompleted($targetSet)) {
            throw new \RuntimeException('Cannot copy media to a completed phase');
        }

        // Verify all media items exist and user owns the proofing that contains them
        $mediaItems = MemoraMedia::whereIn('uuid', $mediaUuids)
            ->with('file')
            ->get();

        if ($mediaItems->count() !== count($mediaUuids)) {
            throw new \RuntimeException('One or more media items not found');
        }

        foreach ($mediaItems as $media) {
            $mediaSet = $media->mediaSet;
            if ($mediaSet) {
                $proofing = $mediaSet->proofing;
                if ($proofing && $proofing->user_uuid !== $userId) {
                    throw new \Exception('Unauthorized: You do not own the proofing containing this media');
                }

                // Check if source phase is completed
                if ($this->isPhaseCompleted($mediaSet)) {
                    throw new \RuntimeException('Cannot copy media from a completed phase');
                }
            } else {
                // Fallback: verify user owns the media directly
                if ($media->user_uuid !== $userId) {
                    throw new \Exception('Unauthorized: You do not own this media');
                }
            }
        }

        // Validate: Prevent copying to the same set (would create duplicates)
        $sourceSetUuids = $mediaItems->pluck('media_set_uuid')->unique();
        if ($sourceSetUuids->contains($targetSetUuid)) {
            throw new \RuntimeException('Cannot copy media to the same set it already belongs to');
        }

        // Get the maximum order for media in the target set
        $maxOrder = MemoraMedia::where('media_set_uuid', $targetSetUuid)
            ->max('order') ?? -1;

        // Create all copied media items in a transaction
        return DB::transaction(function () use ($mediaItems, $targetSetUuid, $maxOrder, $userId) {
            $copiedMedia = [];
            foreach ($mediaItems as $index => $media) {
                if (! $media->user_file_uuid) {
                    // Skip if media doesn't have a user_file_uuid
                    continue;
                }

                // Create new media entry with same user_file_uuid
                $newMedia = MemoraMedia::create([
                    'user_uuid' => $userId,
                    'media_set_uuid' => $targetSetUuid,
                    'user_file_uuid' => $media->user_file_uuid,
                    'order' => $maxOrder + 1 + $index,
                    'is_selected' => false,
                    'is_completed' => false,
                ]);

                // Load the file relationship
                $newMedia->load('file');
                $copiedMedia[] = $newMedia;
            }

            return $copiedMedia;
        });
    }

    /**
     * Get media by UUID for download
     * Returns the media with file relationship loaded
     */
    public function getMediaForDownload(string $mediaUuid): MemoraMedia
    {
        $media = MemoraMedia::where('uuid', $mediaUuid)
            ->with('file')
            ->firstOrFail();

        // Verify the user owns this media
        if ($media->user_uuid !== Auth::user()->uuid) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Media not found');
        }

        // Ensure file relationship is loaded
        if (! $media->file) {
            throw new \RuntimeException('File not found for this media');
        }

        return $media;
    }

    /**
     * Toggle star status for a media item
     *
     * @param  string  $mediaUuid  Media UUID
     * @return array{starred: bool} Returns whether the media is now starred
     */
    public function toggleStar(string $mediaUuid): array
    {
        // Verify the media exists and belongs to the user
        $media = MemoraMedia::where('uuid', $mediaUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        $user = Auth::user();

        // Toggle the star relationship
        $user->starredMedia()->toggle($media->uuid);

        // Check if it's now starred
        $isStarred = $user->starredMedia()->where('media_uuid', $media->uuid)->exists();

        return [
            'starred' => $isStarred,
        ];
    }

    /**
     * Get all starred media for the authenticated user
     *
     * @param  string|null  $sortBy  Sort field and direction (e.g., 'uploaded-desc', 'name-asc')
     * @param  int|null  $page  Page number for pagination
     * @param  int|null  $perPage  Items per page
     * @return array{data: array, pagination: array}|array
     */
    public function getStarredMedia(?string $sortBy = null, ?int $page = null, ?int $perPage = null)
    {
        $user = Auth::user();

        // Get all media starred by the user
        $query = $user->starredMedia()
            ->with(['feedback.replies', 'file', 'mediaSet.selection', 'starredByUsers' => function ($q) use ($user) {
                $q->where('user_uuid', $user->uuid);
            }]);

        // Apply sorting
        if ($sortBy) {
            $this->applyMediaSorting($query, $sortBy);
        } else {
            // Default sort: created_at desc
            $query->orderBy('created_at', 'desc');
        }

        // If pagination is requested, use pagination service
        if ($page !== null && $perPage !== null) {
            $paginationService = app(\App\Services\Pagination\PaginationService::class);
            $perPage = max(1, min(100, $perPage)); // Limit between 1 and 100
            $paginator = $paginationService->paginate($query, $perPage, $page);

            // Reload relationships to ensure they're available
            $relationships = ['feedback.replies', 'file', 'mediaSet.selection', 'starredByUsers'];
            $paginator->getCollection()->load($relationships);

            // Transform items to resources
            $data = \App\Domains\Memora\Resources\V1\MediaResource::collection($paginator->items());

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

        // Non-paginated response
        $media = $query->get();

        return \App\Domains\Memora\Resources\V1\MediaResource::collection($media);
    }

    /**
     * Rename media by updating the UserFile's filename
     * Preserves the original file extension
     *
     * @param  string  $mediaUuid  Media UUID
     * @param  string  $newFilename  New filename (extension will be preserved from original)
     * @return MemoraMedia Updated media with file relationship
     */
    public function renameMedia(string $mediaUuid, string $newFilename, ?string $userId = null): MemoraMedia
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Find media
        $media = MemoraMedia::where('uuid', $mediaUuid)
            ->with('file')
            ->firstOrFail();

        // Verify user owns the proofing that contains this media
        $mediaSet = $media->mediaSet;
        if ($mediaSet) {
            $proofing = $mediaSet->proofing;
            if ($proofing && $proofing->user_uuid !== $userId) {
                throw new \Exception('Unauthorized: You do not own this proofing');
            }

            // Check if phase is completed
            if ($this->isPhaseCompleted($mediaSet)) {
                throw new \RuntimeException('Cannot rename media in a completed phase');
            }
        } else {
            // Fallback: verify user owns the media directly
            if ($media->user_uuid !== $userId) {
                throw new \Exception('Unauthorized: You do not own this media');
            }
        }

        // Ensure file relationship exists
        if (! $media->file) {
            throw new \RuntimeException('File not found for this media');
        }

        // Preserve the original extension
        $originalFilename = $media->file->filename;
        $originalExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);

        // Remove extension from new filename if it exists
        $newFilenameWithoutExt = pathinfo($newFilename, PATHINFO_FILENAME);

        // Reconstruct filename with original extension
        $finalFilename = $originalExtension
            ? $newFilenameWithoutExt.'.'.$originalExtension
            : $newFilenameWithoutExt;

        // Update the UserFile's filename
        $media->file->update([
            'filename' => $finalFilename,
        ]);

        // Reload the file relationship to get updated data
        $media->load('file');

        return $media;
    }

    /**
     * Replace media file by updating the user_file_uuid to point to a new UserFile
     *
     * @param  string  $mediaUuid  Media UUID
     * @param  string  $newUserFileUuid  New UserFile UUID
     * @return MemoraMedia Updated media with file relationship
     */
    public function replaceMedia(string $mediaUuid, string $newUserFileUuid, ?string $userId = null): MemoraMedia
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Find media
        $media = MemoraMedia::where('uuid', $mediaUuid)->firstOrFail();

        // Verify user owns the proofing that contains this media
        $mediaSet = $media->mediaSet;
        if ($mediaSet) {
            $proofing = $mediaSet->proofing;
            if ($proofing && $proofing->user_uuid !== $userId) {
                throw new \Exception('Unauthorized: You do not own this proofing');
            }

            // Check if phase is completed
            if ($this->isPhaseCompleted($mediaSet)) {
                throw new \RuntimeException('Cannot replace media in a completed phase');
            }
        } else {
            // Fallback: verify user owns the media directly
            if ($media->user_uuid !== $userId) {
                throw new \Exception('Unauthorized: You do not own this media');
            }
        }

        // Verify the new UserFile exists and belongs to the authenticated user
        $newUserFile = \App\Models\UserFile::query()
            ->where('uuid', $newUserFileUuid)
            ->where('user_uuid', $userId)
            ->firstOrFail();

        // Update the media to point to the new UserFile
        $media->update([
            'user_file_uuid' => $newUserFileUuid,
        ]);

        // Reload the file relationship to get updated data
        $media->load('file');

        return $media;
    }
}
