<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaFeedback;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraSelection;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get phase media
     * Uses media sets to find media for a phase (proofing, selection, or collection)
     */
    public function getPhaseMedia(string $phaseType, string $phaseId, ?string $setUuid = null)
    {
        // Find media sets for this phase
        $setQuery = MemoraMediaSet::query();

        if ($phaseType === 'proofing') {
            $setQuery->where('proof_uuid', $phaseId);
        } elseif ($phaseType === 'selection') {
            $setQuery->where('selection_uuid', $phaseId);
        } elseif ($phaseType === 'collection') {
            $setQuery->where('collection_uuid', $phaseId);
        } else {
            return collect();
        }

        if ($setUuid) {
            $setQuery->where('uuid', $setUuid);
        }

        $setUuids = $setQuery->pluck('uuid');

        if ($setUuids->isEmpty()) {
            return collect();
        }

        // Get media from those sets
        $query = MemoraMedia::whereIn('media_set_uuid', $setUuids)
            ->with(['feedback.replies', 'file'])
            ->orderBy('order');

        return $query->get();
    }

    /**
     * Move media between phases
     * Uses media sets to move media from one phase to another
     */
    public function moveBetweenPhases(array $mediaIds, string $fromPhase, string $fromPhaseId, string $toPhase, string $toPhaseId): array
    {
        // Find target media set for the destination phase
        $targetSetQuery = MemoraMediaSet::query();

        if ($toPhase === 'proofing') {
            $targetSetQuery->where('proof_uuid', $toPhaseId);
        } elseif ($toPhase === 'selection') {
            $targetSetQuery->where('selection_uuid', $toPhaseId);
        } elseif ($toPhase === 'collection') {
            $targetSetQuery->where('collection_uuid', $toPhaseId);
        } else {
            throw new \InvalidArgumentException("Invalid phase type: {$toPhase}");
        }

        // Get or create the first set for the target phase
        $targetSet = $targetSetQuery->first();
        if (! $targetSet) {
            // Create a default set if none exists
            $targetSet = MemoraMediaSet::create([
                'user_uuid' => Auth::user()->uuid,
                'proof_uuid' => $toPhase === 'proofing' ? $toPhaseId : null,
                'selection_uuid' => $toPhase === 'selection' ? $toPhaseId : null,
                'collection_uuid' => $toPhase === 'collection' ? $toPhaseId : null,
                'name' => 'Default Set',
                'order' => 0,
            ]);
        }

        // Move media to the target set
        $moved = MemoraMedia::whereIn('uuid', $mediaIds)->update([
            'media_set_uuid' => $targetSet->uuid,
        ]);

        $media = MemoraMedia::whereIn('uuid', $mediaIds)->get();

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

        // Load media set with appropriate parent relationship (selection, rawFile, proof, or collection)
        $media->load('mediaSet');
        $set = $media->mediaSet;
        
        if (!$set) {
            throw new \RuntimeException('Media set not found for this media item.');
        }

        // If marking as selected, validate against limits based on parent type
        if ($isSelected) {
            $setId = $set->uuid;
            
            // Check if media set belongs to a raw file
            if ($set->raw_file_uuid) {
                $rawFileLimitService = app(\App\Domains\Memora\Services\RawFileLimitService::class);
                
                // Get current selected count for this set
                $currentCount = \App\Domains\Memora\Models\MemoraMedia::query()
                    ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                    ->where('memora_media_sets.uuid', $setId)
                    ->where('memora_media.is_selected', true)
                    ->whereNull('memora_media.deleted_at')
                    ->count();

                // Check if selection is allowed
                if (!$rawFileLimitService->checkRawFileLimit($set->raw_file_uuid, $setId, $currentCount)) {
                    throw new \RuntimeException('Raw file limit reached. Cannot select more items.');
                }
            } elseif ($set->selection_uuid) {
                // Check if media set belongs to a selection
                $media->load('mediaSet.selection');
                $selection = $set->selection;
                
                if ($selection) {
                    $selectionLimitService = app(\App\Domains\Memora\Services\SelectionLimitService::class);
                    $selectionId = $selection->uuid;

                    // Get current selected count for this set
                    $currentCount = \App\Domains\Memora\Models\MemoraMedia::query()
                        ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
                        ->where('memora_media_sets.uuid', $setId)
                        ->where('memora_media.is_selected', true)
                        ->whereNull('memora_media.deleted_at')
                        ->count();

                    // Check if selection is allowed
                    if (!$selectionLimitService->checkSelectionLimit($selectionId, $setId, $currentCount)) {
                        throw new \RuntimeException('Selection limit reached. Cannot select more items.');
                    }
                }
            }
            // Note: Proof and collection limits can be added here if needed
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

        // Refresh to ensure relationships are loaded
        $feedback->refresh();

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

        // Refresh to ensure relationships are loaded
        $feedback->refresh();

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

    public function getSetMedia(string $setUuid, ?string $sortBy = null, ?int $page = null, ?int $perPage = null, ?string $collectionUuid = null, ?string $userEmail = null, ?bool $isClientVerified = false)
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

        // Filter private media: show private media only to the user who marked it as private
        // For authenticated users or verified clients, show all media
        // For public users with email, show only media they marked private (or public media)
        if (! Auth::check() && ! $isClientVerified) {
            if ($userEmail) {
                // Show public media OR private media marked by this user's email
                $emailLower = strtolower(trim($userEmail));
                $query->where(function ($q) use ($emailLower) {
                    $q->where('is_private', false)
                        ->orWhere(function ($subQ) use ($emailLower) {
                            $subQ->where('is_private', true)
                                ->where('marked_private_by_email', $emailLower);
                        });
                });
            } else {
                // No email provided - hide all private media
                $query->where('is_private', false);
            }
        }

        // Only load starredByUsers if user is authenticated
        if (Auth::check()) {
            $query->with(['starredByUsers' => function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }]);
        }

        // Load collection favourites if collection UUID is provided (for public collections)
        $favouriteMediaUuids = [];
        if ($collectionUuid) {
            $favouriteQuery = \App\Domains\Memora\Models\MemoraCollectionFavourite::where('collection_uuid', $collectionUuid);

            if (Auth::check()) {
                $favouriteQuery->where('user_uuid', Auth::user()->uuid);
            } elseif ($userEmail) {
                $favouriteQuery->where('email', strtolower(trim($userEmail)));
            }

            $favouriteMediaUuids = $favouriteQuery->pluck('media_uuid')->toArray();
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

            // Attach collection favourite status to media items
            if ($collectionUuid && ! empty($favouriteMediaUuids)) {
                $paginator->getCollection()->each(function ($media) use ($favouriteMediaUuids) {
                    $media->setAttribute('isCollectionFavourited', in_array($media->uuid, $favouriteMediaUuids));
                });
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

        // Attach collection favourite status to media items
        if ($collectionUuid && ! empty($favouriteMediaUuids)) {
            $media->each(function ($item) use ($favouriteMediaUuids) {
                $item->setAttribute('isCollectionFavourited', in_array($item->uuid, $favouriteMediaUuids));
            });
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

        // Check if collection has watermark and apply it
        if ($set->collection_uuid) {
            $collection = MemoraCollection::query()
                ->where('uuid', $set->collection_uuid)
                ->first();

            if ($collection && $collection->watermark_uuid) {
                try {
                    $this->applyWatermark($media->uuid, $collection->watermark_uuid);
                    $media->refresh();
                } catch (\Exception $e) {
                    // Log error but don't fail the upload
                    Log::warning('Failed to apply collection watermark to media', [
                        'media_uuid' => $media->uuid,
                        'watermark_uuid' => $collection->watermark_uuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Load the file relationship for the response
        $media->load('file');

        // Update phase storage
        $this->updatePhaseStorage($set);

        return $media;
    }

    /**
     * Check if a phase (proofing, selection, or collection) is completed
     */
    protected function isPhaseCompleted(MemoraMediaSet $mediaSet): bool
    {
        if ($mediaSet->proof_uuid) {
            $proofing = MemoraProofing::where('uuid', $mediaSet->proof_uuid)->first();

            return $proofing && $proofing->status->value === 'completed';
        }

        if ($mediaSet->raw_file_uuid) {
            $rawFile = \App\Domains\Memora\Models\MemoraRawFile::where('uuid', $mediaSet->raw_file_uuid)->first();

            return $rawFile && $rawFile->status->value === 'completed';
        }

        if ($mediaSet->selection_uuid) {
            $selection = MemoraSelection::where('uuid', $mediaSet->selection_uuid)->first();

            return $selection && $selection->status->value === 'completed';
        }

        if ($mediaSet->collection_uuid) {
            $collection = MemoraCollection::where('uuid', $mediaSet->collection_uuid)->first();

            return $collection && $collection->status->value === 'completed';
        }

        return false;
    }

    /**
     * Update phase storage when media is uploaded/deleted
     */
    protected function updatePhaseStorage(MemoraMediaSet $mediaSet): void
    {
        try {
            $storageService = app(\App\Services\Storage\UserStorageService::class);

            if ($mediaSet->proof_uuid) {
                $storageService->getPhaseStorageUsed($mediaSet->proof_uuid, 'proofing');
            } elseif ($mediaSet->selection_uuid) {
                $storageService->getPhaseStorageUsed($mediaSet->selection_uuid, 'selection');
            } elseif ($mediaSet->collection_uuid) {
                $storageService->getPhaseStorageUsed($mediaSet->collection_uuid, 'collection');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update phase storage', [
                'media_set_uuid' => $mediaSet->uuid,
                'error' => $e->getMessage(),
            ]);
        }
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

        $media = MemoraMedia::withTrashed()->with('file')->where('uuid', $mediaId)->firstOrFail();

        // Verify user owns the phase (proofing, selection, or collection) that contains this media
        $mediaSet = $media->mediaSet;
        if ($mediaSet) {
            // Check ownership based on phase type
            $isAuthorized = false;
            if ($mediaSet->proof_uuid) {
                $proofing = $mediaSet->proofing;
                if ($proofing && $proofing->user_uuid === $userId) {
                    $isAuthorized = true;
                }
            } elseif ($mediaSet->selection_uuid) {
                $selection = $mediaSet->selection;
                if ($selection && $selection->user_uuid === $userId) {
                    $isAuthorized = true;
                }
            } elseif ($mediaSet->collection_uuid) {
                $collection = $mediaSet->collection;
                if ($collection && $collection->user_uuid === $userId) {
                    $isAuthorized = true;
                }
            }

            if (! $isAuthorized) {
                throw new \Exception('Unauthorized: You do not own this media');
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

        // Delete files from storage if media has a file
        if ($media->file) {
            try {
                $pathsToDelete = [];
                if ($media->file->path) {
                    $pathsToDelete[] = $media->file->path;
                }
                if ($media->file->metadata && isset($media->file->metadata['variants'])) {
                    foreach ($media->file->metadata['variants'] as $variantPath) {
                        if ($variantPath && ! in_array($variantPath, $pathsToDelete)) {
                            $pathsToDelete[] = $variantPath;
                        }
                    }
                }

                if (! empty($pathsToDelete)) {
                    $this->uploadService->deleteFiles($pathsToDelete[0], count($pathsToDelete) > 1 ? array_slice($pathsToDelete, 1) : null);
                }

                // Check if UserFile is used by other non-deleted media records
                $otherMediaCount = \App\Domains\Memora\Models\MemoraMedia::where('user_file_uuid', $media->file->uuid)
                    ->where('uuid', '!=', $mediaId)
                    ->whereNull('deleted_at')
                    ->count();
                if ($otherMediaCount === 0) {
                    // Delete the UserFile (this will trigger the deleted event and decrement storage)
                    $media->file->delete();
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to delete media files from storage', [
                    'media_uuid' => $mediaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Check if media is already soft-deleted and permanent deletion is allowed
        $allowPermanentDeletion = env('ALLOW_PERMANENT_MEDIA_DELETION', false);
        if ($media->trashed() && $allowPermanentDeletion) {
            return $media->forceDelete();
        }

        // Get media set before deletion for phase storage update
        $mediaSet = $media->mediaSet;

        // Delete the media record (soft delete)
        // Note: We don't delete the user_file record as it may be used elsewhere
        // The user_file will remain in the database for potential recovery
        $deleted = $media->delete();

        // Update phase storage after deletion
        if ($mediaSet) {
            $this->updatePhaseStorage($mediaSet);
        }

        return $deleted;
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
     * Verifies user owns the media (directly or through phase ownership)
     */
    public function getMediaForDownload(string $mediaUuid): MemoraMedia
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $media = MemoraMedia::where('uuid', $mediaUuid)
            ->with('file')
            ->firstOrFail();

        // Verify user owns the phase (proofing, selection, or collection) that contains this media
        $mediaSet = $media->mediaSet;
        if ($mediaSet) {
            // Check ownership based on phase type
            $isAuthorized = false;
            if ($mediaSet->proof_uuid) {
                $proofing = $mediaSet->proofing;
                if ($proofing && $proofing->user_uuid === $userId) {
                    $isAuthorized = true;
                }
            } elseif ($mediaSet->selection_uuid) {
                $selection = $mediaSet->selection;
                if ($selection && $selection->user_uuid === $userId) {
                    $isAuthorized = true;
                }
            } elseif ($mediaSet->collection_uuid) {
                $collection = $mediaSet->collection;
                if ($collection && $collection->user_uuid === $userId) {
                    $isAuthorized = true;
                }
            }

            if (! $isAuthorized) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Media not found');
            }
        } else {
            // Fallback: verify user owns the media directly
            if ($media->user_uuid !== $userId) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Media not found');
            }
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
     * Get all media for the authenticated user
     */
    public function getUserMedia(?string $sortBy = null, ?int $page = null, ?int $perPage = null)
    {
        $user = Auth::user();

        // Get all media owned by the user
        $query = MemoraMedia::where('user_uuid', $user->uuid)
            ->with([
                'feedback.replies',
                'file',
                'mediaSet.selection',
                'mediaSet.proofing',
                'mediaSet.collection',
                'starredByUsers' => function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid);
                },
            ]);

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
            $relationships = [
                'feedback.replies',
                'file',
                'mediaSet.selection',
                'mediaSet.proofing',
                'mediaSet.collection',
                'starredByUsers',
            ];
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
     * Get all featured media for the authenticated user
     */
    public function getFeaturedMedia(?string $sortBy = null, ?int $page = null, ?int $perPage = null)
    {
        $user = Auth::user();

        // Get all media featured by the user
        $query = MemoraMedia::where('user_uuid', $user->uuid)
            ->where('is_featured', true)
            ->with(['feedback.replies', 'file', 'mediaSet.collection', 'starredByUsers' => function ($q) use ($user) {
                $q->where('user_uuid', $user->uuid);
            }]);

        // Apply sorting
        if ($sortBy) {
            // Map featured_at sort to actual column
            if (str_contains($sortBy, 'featured_at')) {
                $direction = str_ends_with($sortBy, '-desc') ? 'desc' : 'asc';
                $query->orderBy('featured_at', $direction);
            } else {
                $this->applyMediaSorting($query, $sortBy);
            }
        } else {
            // Default sort: featured_at desc
            $query->orderBy('featured_at', 'desc');
        }

        // If pagination is requested, use pagination service
        if ($page !== null && $perPage !== null) {
            $paginationService = app(\App\Services\Pagination\PaginationService::class);
            $perPage = max(1, min(100, $perPage)); // Limit between 1 and 100
            $paginator = $paginationService->paginate($query, $perPage, $page);

            // Reload relationships to ensure they're available
            $relationships = ['feedback.replies', 'file', 'mediaSet.collection', 'starredByUsers'];
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

    /**
     * Apply watermark to media
     */
    public function applyWatermark(string $mediaUuid, string $watermarkUuid, ?string $userId = null, bool $previewStyle = false): MemoraMedia
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $media = MemoraMedia::where('uuid', $mediaUuid)->firstOrFail();
        if ($media->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own this media');
        }

        if (! $media->file) {
            throw new \RuntimeException('File not found for this media');
        }

        $watermark = \App\Domains\Memora\Models\MemoraWatermark::where('uuid', $watermarkUuid)
            ->where('user_uuid', $userId)
            ->with('imageFile')
            ->firstOrFail();

        // Get original file - if watermark already exists, use original_file_uuid, otherwise use current file
        $originalFileUuid = $media->original_file_uuid ?? $media->user_file_uuid;
        $originalFile = \App\Models\UserFile::where('uuid', $originalFileUuid)->firstOrFail();

        // If media already has a watermark, we need to delete the old watermarked file
        $oldWatermarkedFileUuid = null;
        if ($media->watermark_uuid && $media->user_file_uuid !== $originalFileUuid) {
            $oldWatermarkedFileUuid = $media->user_file_uuid;
        }

        $fileType = $originalFile->type?->value ?? $originalFile->type;

        try {
            if ($fileType === 'video') {
                // Handle video watermarking
                $this->applyWatermarkToVideo($media, $originalFile, $watermark, $watermarkUuid, $userId, $previewStyle, $oldWatermarkedFileUuid);
            } else {
                // Handle image watermarking
                $originalUrl = $originalFile->url;
                $tempPath = $this->downloadImageToTemp($originalUrl);

                // Calculate media dimensions for watermark positioning
                $imageInfo = @getimagesize($tempPath);
                if (! $imageInfo) {
                    throw new \RuntimeException('Failed to get image dimensions');
                }
                $mediaWidth = $imageInfo[0];
                $mediaHeight = $imageInfo[1];

                // Apply watermark (preview style only for text watermarks, otherwise use standard)
                $watermarkType = $watermark->type instanceof \App\Domains\Memora\Enums\WatermarkTypeEnum
                    ? $watermark->type
                    : \App\Domains\Memora\Enums\WatermarkTypeEnum::from($watermark->type ?? 'text');

                $usePreviewStyle = $previewStyle && $watermarkType === \App\Domains\Memora\Enums\WatermarkTypeEnum::TEXT;

                $watermarkedPath = $usePreviewStyle
                    ? $this->applyWatermarkInPreviewStyle($tempPath, $watermark, $mediaWidth, $mediaHeight)
                    : $this->applyWatermarkToImage($tempPath, $watermark, $mediaWidth, $mediaHeight);

                // Upload watermarked image
                $uploadService = app(\App\Services\Image\ImageUploadService::class);
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    $watermarkedPath,
                    basename($watermarkedPath),
                    mime_content_type($watermarkedPath),
                    null,
                    true
                );

                $uploadResult = $uploadService->uploadImage($uploadedFile, [
                    'context' => 'watermarked-media',
                    'visibility' => 'public',
                ]);

                // Create UserFile for watermarked image
                $totalSizeWithVariants = $uploadResult['meta']['total_size_with_variants'] ?? filesize($watermarkedPath);
                $watermarkedFile = \App\Models\UserFile::create([
                    'user_uuid' => $userId,
                    'url' => $uploadResult['variants']['original'] ?? $uploadResult['variants']['large'] ?? '',
                    'path' => 'uploads/images/'.$uploadResult['uuid'],
                    'type' => 'image',
                    'filename' => $originalFile->filename,
                    'mime_type' => mime_content_type($watermarkedPath),
                    'size' => filesize($watermarkedPath),
                    'width' => $uploadResult['meta']['width'] ?? null,
                    'height' => $uploadResult['meta']['height'] ?? null,
                    'metadata' => [
                        'uuid' => $uploadResult['uuid'],
                        'variants' => $uploadResult['variants'],
                        'variant_sizes' => $uploadResult['variant_sizes'] ?? [],
                        'total_size_with_variants' => $totalSizeWithVariants,
                    ],
                ]);

                // Update cached storage
                $storageService = app(\App\Services\Storage\UserStorageService::class);
                $storageService->incrementStorage($userId, $totalSizeWithVariants);

                // Update media with new watermarked file
                $media->update([
                    'user_file_uuid' => $watermarkedFile->uuid,
                    'watermark_uuid' => $watermarkUuid,
                    'original_file_uuid' => $originalFileUuid,
                ]);
            }

            // Delete old watermarked file if it exists (cleanup) - for images only
            if ($fileType !== 'video' && $oldWatermarkedFileUuid) {
                try {
                    $oldWatermarkedFile = \App\Models\UserFile::where('uuid', $oldWatermarkedFileUuid)->first();
                    if ($oldWatermarkedFile) {
                        // Collect all file paths to delete (main file + variants)
                        $pathsToDelete = [];
                        if ($oldWatermarkedFile->path) {
                            $pathsToDelete[] = $oldWatermarkedFile->path;
                        }
                        if ($oldWatermarkedFile->metadata && isset($oldWatermarkedFile->metadata['variants'])) {
                            foreach ($oldWatermarkedFile->metadata['variants'] as $variantPath) {
                                if ($variantPath && ! in_array($variantPath, $pathsToDelete)) {
                                    $pathsToDelete[] = $variantPath;
                                }
                            }
                        }

                        // Delete files using UploadService
                        if (! empty($pathsToDelete)) {
                            $uploadServiceForDeletion = app(\App\Services\Upload\UploadService::class);
                            $uploadServiceForDeletion->deleteFiles($pathsToDelete[0], count($pathsToDelete) > 1 ? array_slice($pathsToDelete, 1) : null);
                        }

                        // Delete the UserFile record
                        $oldWatermarkedFile->delete();
                    }
                } catch (\Exception $e) {
                    // Log but don't fail if cleanup fails
                    \Illuminate\Support\Facades\Log::warning('Failed to delete old watermarked file', [
                        'file_uuid' => $oldWatermarkedFileUuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Reload relationships to ensure file is fresh
            $media->refresh();
            $media->load('file');

            return $media;
        } finally {
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }
            if (isset($watermarkedPath) && file_exists($watermarkedPath)) {
                unlink($watermarkedPath);
            }
        }
    }

    /**
     * Remove watermark from media
     */
    public function removeWatermark(string $mediaUuid, ?string $userId = null): MemoraMedia
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $media = MemoraMedia::where('uuid', $mediaUuid)->firstOrFail();
        if ($media->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own this media');
        }

        if (! $media->original_file_uuid) {
            throw new \RuntimeException('No original file found to restore');
        }

        // Restore original file
        $media->update([
            'user_file_uuid' => $media->original_file_uuid,
            'watermark_uuid' => null,
            'original_file_uuid' => null,
        ]);

        // Reload relationships to ensure file is fresh
        $media->refresh();
        $media->load('file');

        return $media;
    }

    /**
     * Download image from URL to temporary file
     */
    protected function downloadImageToTemp(string $url): string
    {
        // Detect file extension from URL or content
        $tempPath = sys_get_temp_dir().'/'.uniqid('watermark_', true);

        // Check if URL is a storage path (starts with storage:// or is a relative path)
        if (str_starts_with($url, 'storage://') || (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://'))) {
            // Try to get from storage
            $path = str_replace('storage://', '', $url);
            $disksToCheck = ['public', 'local', 's3', 'r2'];

            foreach ($disksToCheck as $disk) {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($path)) {
                        $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($path);
                        if ($content !== false) {
                            // Detect image type
                            $imageInfo = @getimagesizefromstring($content);
                            if ($imageInfo !== false && isset($imageInfo['mime'])) {
                                $extension = match ($imageInfo['mime']) {
                                    'image/jpeg', 'image/jpg' => 'jpg',
                                    'image/png' => 'png',
                                    'image/gif' => 'gif',
                                    'image/webp' => 'webp',
                                    default => 'jpg',
                                };
                                $tempPath .= '.'.$extension;
                                file_put_contents($tempPath, $content);

                                return $tempPath;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next disk
                    continue;
                }
            }

            throw new \RuntimeException('Watermark image not found in storage: '.$url);
        }

        // Handle HTTP/HTTPS URLs
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => true,
                'ignore_errors' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $error = error_get_last();
            throw new \RuntimeException('Failed to download image from URL: '.$url.($error ? ' - '.$error['message'] : ''));
        }

        if (empty($content)) {
            throw new \RuntimeException('Downloaded image is empty from URL: '.$url);
        }

        // Try to detect image type from content
        $imageInfo = @getimagesizefromstring($content);
        if ($imageInfo === false || ! isset($imageInfo['mime'])) {
            throw new \RuntimeException('Invalid image content. URL may not point to a valid image: '.$url);
        }

        $extension = match ($imageInfo['mime']) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };

        $tempPath .= '.'.$extension;
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * Apply watermark to image file
     *
     * @param  string  $imagePath  Path to the image file
     * @param  \App\Domains\Memora\Models\MemoraWatermark  $watermark  Watermark to apply
     * @param  int|null  $mediaWidth  Optional: pre-calculated media width (if null, will be calculated from file)
     * @param  int|null  $mediaHeight  Optional: pre-calculated media height (if null, will be calculated from file)
     */
    protected function applyWatermarkToImage(string $imagePath, \App\Domains\Memora\Models\MemoraWatermark $watermark, ?int $mediaWidth = null, ?int $mediaHeight = null): string
    {
        $outputPath = sys_get_temp_dir().'/'.uniqid('watermarked_', true).'.jpg';

        // Load image using GD
        $imageInfo = getimagesize($imagePath);
        if (! $imageInfo) {
            throw new \RuntimeException('Invalid image file');
        }

        $mimeType = $imageInfo['mime'];

        $gdImage = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($imagePath),
            'image/png' => imagecreatefrompng($imagePath),
            'image/gif' => imagecreatefromgif($imagePath),
            'image/webp' => imagecreatefromwebp($imagePath),
            default => throw new \RuntimeException('Unsupported image type: '.$mimeType),
        };

        if (! $gdImage) {
            throw new \RuntimeException('Failed to create GD image');
        }

        // Use provided dimensions or get actual GD image dimensions for watermark positioning
        $imageWidth = $mediaWidth ?? imagesx($gdImage);
        $imageHeight = $mediaHeight ?? imagesy($gdImage);

        $opacity = ($watermark->opacity ?? 80) / 100;
        $position = $watermark->position instanceof \App\Domains\Memora\Enums\WatermarkPositionEnum
            ? $watermark->position->value
            : ($watermark->position ?? 'bottom-right');

        $watermarkType = $watermark->type instanceof \App\Domains\Memora\Enums\WatermarkTypeEnum
            ? $watermark->type
            : \App\Domains\Memora\Enums\WatermarkTypeEnum::from($watermark->type ?? 'text');

        if ($watermarkType === \App\Domains\Memora\Enums\WatermarkTypeEnum::TEXT) {
            if (empty($watermark->text)) {
                throw new \RuntimeException('Text watermark requires text content');
            }
            $this->applyTextWatermarkGd($gdImage, $watermark, $imageWidth, $imageHeight, $opacity, $position);
        } elseif ($watermarkType === \App\Domains\Memora\Enums\WatermarkTypeEnum::IMAGE) {
            if (! $watermark->imageFile || ! $watermark->imageFile->url) {
                throw new \RuntimeException('Image watermark requires an image file');
            }
            $this->applyImageWatermarkGd($gdImage, $watermark, $imageWidth, $imageHeight, $opacity, $position);
        } else {
            throw new \RuntimeException('Invalid watermark type: '.($watermark->type ?? 'unknown'));
        }

        imagejpeg($gdImage, $outputPath, 90);
        imagedestroy($gdImage);

        return $outputPath;
    }

    /**
     * Apply text watermark using GD
     */
    protected function applyTextWatermarkGd(
        $gdImage,
        \App\Domains\Memora\Models\MemoraWatermark $watermark,
        int $imageWidth,
        int $imageHeight,
        float $opacity,
        string $position
    ): void {
        $text = $watermark->text ?? '';
        if (empty($text)) {
            return;
        }

        // Apply text transform
        if ($watermark->text_transform instanceof \App\Domains\Memora\Enums\TextTransformEnum) {
            $transform = $watermark->text_transform;
            if ($transform === \App\Domains\Memora\Enums\TextTransformEnum::UPPERCASE) {
                $text = strtoupper($text);
            } elseif ($transform === \App\Domains\Memora\Enums\TextTransformEnum::LOWERCASE) {
                $text = strtolower($text);
            }
        }

        // Enhanced scaling: padding-aware with diagonal fallback for extreme aspect ratios
        $scalePercent = ($watermark->scale ?? 50) / 100; // Convert to 0.0-1.0

        // Calculate padding (5% of min dimension, minimum 20px)
        $basePadding = min($imageWidth, $imageHeight) * 0.05;
        $padding = max(20, (int) $basePadding);

        // Calculate usable dimensions (accounting for padding)
        $usableWidth = max($imageWidth - ($padding * 2), $imageWidth * 0.9);
        $usableHeight = max($imageHeight - ($padding * 2), $imageHeight * 0.9);
        $minDimension = min($usableWidth, $usableHeight);

        // For extreme aspect ratios (panoramic/tall), use diagonal as fallback
        $aspectRatio = $imageWidth / $imageHeight;
        $isExtremeAspectRatio = $aspectRatio > 3.0 || $aspectRatio < 0.33;

        if ($isExtremeAspectRatio) {
            // Use diagonal-based scaling for extreme aspect ratios
            $diagonal = sqrt($imageWidth * $imageWidth + $imageHeight * $imageHeight);
            $baseSize = $diagonal * 0.05; // 5% of diagonal
            $maxFontSize = $baseSize * 2; // Max 10% of diagonal
            $baseFontSize = $baseSize * $scalePercent;
        } else {
            // Standard min-dimension approach
            $maxFontSize = $minDimension * 0.1; // Max 10% of min dimension
            $baseFontSize = $minDimension * $scalePercent * 0.1; // Scale% of max font size
        }

        $fontSize = min(max($baseFontSize, 12), $maxFontSize); // Min 12px, enforce max

        // Get colors
        $fontColor = $watermark->font_color ?? '#FFFFFF';
        $fontRgb = $this->hexToRgb($fontColor);
        $bgColor = $watermark->background_color ?? null;
        $bgRgb = $bgColor ? $this->hexToRgb($bgColor) : null;

        $padding = $watermark->padding ?? 0;
        $letterSpacing = $watermark->letter_spacing ?? 0;
        $lineHeight = $watermark->line_height ?? 1.2;

        // Try to find TTF font using watermark's font_family
        $fontFamily = $watermark->font_family;
        if ($fontFamily) {
            \Log::debug('Watermark font_family lookup', [
                'watermark_uuid' => $watermark->uuid,
                'font_family' => $fontFamily,
                'font_family_type' => gettype($fontFamily),
            ]);
        }
        $fontPath = $this->findSystemFont($fontFamily);
        if ($fontPath && $fontFamily) {
            \Log::debug('Font found', ['font_family' => $fontFamily, 'font_path' => $fontPath]);
        } elseif ($fontFamily) {
            \Log::warning('Font not found', ['font_family' => $fontFamily]);
        }

        // Use high-resolution rendering (3x) for smoother text scaling
        $renderScale = 3;
        $renderFontSize = $fontSize * $renderScale;

        // Estimate text width - use TTF if available, otherwise GD font approximation
        if ($fontPath && function_exists('imagettfbbox')) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            // For cursive/italic fonts, bounding box might not include full width
            // Add extra padding (72% of width) to account for font rendering quirks and prevent clipping
            $baseTextWidth = abs($bbox[4] - $bbox[0]);
            $letterSpacingWidth = (strlen($text) - 1) * $letterSpacing;
            $textWidth = (int) (($baseTextWidth + $letterSpacingWidth) * 1.72); // Add 72% buffer
        } else {
            // For GD fonts, approximate: font size * 0.55-0.65 per character
            $avgCharWidth = $fontSize * 0.6;
            $textWidth = (int) ((strlen($text) * $avgCharWidth) + (strlen($text) - 1) * $letterSpacing);
        }

        // Apply max width constraint (80% of image width) - same as frontend
        $maxTextWidth = $imageWidth * 0.8;
        if ($textWidth > $maxTextWidth) {
            $scaleFactor = $maxTextWidth / $textWidth;
            $fontSize = $fontSize * $scaleFactor;
            $textWidth = $maxTextWidth;
        }

        $totalWidth = $textWidth + ($padding * 2);
        // Increase height buffer to account for descenders and font rendering quirks
        // Use actual text height from bbox if available, otherwise use font size with extra buffer
        if ($fontPath && function_exists('imagettfbbox')) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $actualTextHeight = abs($bbox[7] - $bbox[1]);
            // Add 50% buffer to height for descenders and rendering safety
            $totalHeight = (int) (($actualTextHeight * 1.5) + ($padding * 2));
        } else {
            $totalHeight = (int) (($fontSize * $lineHeight * 1.5) + ($padding * 2));
        }

        // Create watermark image with background
        $textImage = imagecreatetruecolor((int) $totalWidth, (int) $totalHeight);
        imagealphablending($textImage, false);
        imagesavealpha($textImage, true);
        $transparent = imagecolorallocatealpha($textImage, 0, 0, 0, 127);
        imagefill($textImage, 0, 0, $transparent);

        $bgAlpha = (int) ((1 - $opacity) * 127);
        $borderRadius = $watermark->border_radius ?? 0;

        // Draw background if specified
        if ($bgRgb) {
            $bgColorRes = imagecolorallocatealpha($textImage, $bgRgb['r'], $bgRgb['g'], $bgRgb['b'], $bgAlpha);

            if ($borderRadius > 0) {
                // Draw rounded rectangle (simplified)
                imagefilledrectangle($textImage, $borderRadius, 0, (int) $totalWidth - $borderRadius - 1, (int) $totalHeight - 1, $bgColorRes);
                imagefilledrectangle($textImage, 0, $borderRadius, (int) $totalWidth - 1, (int) $totalHeight - $borderRadius - 1, $bgColorRes);
            } else {
                imagefilledrectangle($textImage, 0, 0, (int) $totalWidth - 1, (int) $totalHeight - 1, $bgColorRes);
            }
        }

        // Draw border if specified (even without background)
        if (($watermark->border_width ?? 0) > 0 && $watermark->border_color) {
            $borderRgb = $this->hexToRgb($watermark->border_color);
            $borderColorRes = imagecolorallocatealpha($textImage, $borderRgb['r'], $borderRgb['g'], $borderRgb['b'], $bgAlpha);
            $borderWidth = $watermark->border_width;

            if ($borderRadius > 0) {
                // Draw rounded border
                for ($i = 0; $i < $borderWidth; $i++) {
                    $x = $i;
                    $y = $i;
                    $w = (int) $totalWidth - 1 - ($i * 2);
                    $h = (int) $totalHeight - 1 - ($i * 2);
                    $r = max(0, $borderRadius - $i);

                    // Simplified rounded rectangle border
                    if ($r > 0) {
                        // Top and bottom edges
                        imageline($textImage, $x + $r, $y, $x + $w - $r, $y, $borderColorRes);
                        imageline($textImage, $x + $r, $y + $h, $x + $w - $r, $y + $h, $borderColorRes);
                        // Left and right edges
                        imageline($textImage, $x, $y + $r, $x, $y + $h - $r, $borderColorRes);
                        imageline($textImage, $x + $w, $y + $r, $x + $w, $y + $h - $r, $borderColorRes);
                        // Corners (simplified)
                        imagearc($textImage, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $borderColorRes);
                        imagearc($textImage, $x + $w - $r, $y + $r, $r * 2, $r * 2, 270, 360, $borderColorRes);
                        imagearc($textImage, $x + $r, $y + $h - $r, $r * 2, $r * 2, 90, 180, $borderColorRes);
                        imagearc($textImage, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, 0, 90, $borderColorRes);
                    } else {
                        imagerectangle($textImage, $x, $y, $x + $w, $y + $h, $borderColorRes);
                    }
                }
            } else {
                // Draw rectangular border
                for ($i = 0; $i < $borderWidth; $i++) {
                    imagerectangle($textImage, $i, $i, (int) $totalWidth - 1 - $i, (int) $totalHeight - 1 - $i, $borderColorRes);
                }
            }
        }

        // Create high-res text canvas
        $textCanvasWidth = (int) ($textWidth * $renderScale);
        // Increase canvas height to prevent bottom clipping
        if ($fontPath && function_exists('imagettfbbox')) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $actualTextHeight = abs($bbox[7] - $bbox[1]);
            $textCanvasHeight = (int) (($actualTextHeight * 1.5) * $renderScale);
        } else {
            $textCanvasHeight = (int) (($fontSize * $lineHeight * 1.5) * $renderScale);
        }
        $textCanvas = imagecreatetruecolor($textCanvasWidth, $textCanvasHeight);
        imagealphablending($textCanvas, false);
        imagesavealpha($textCanvas, true);
        $transparentCanvas = imagecolorallocatealpha($textCanvas, 0, 0, 0, 127);
        imagefill($textCanvas, 0, 0, $transparentCanvas);

        // Render text using TTF font if available, otherwise GD font
        if ($fontPath && function_exists('imagettftext')) {
            // Use TTF font - create larger canvas to prevent clipping
            $bbox = imagettfbbox($renderFontSize, 0, $fontPath, $text);
            $baseTextWidth = abs($bbox[4] - $bbox[0]);
            $baseTextHeight = abs($bbox[7] - $bbox[1]);
            // Add very generous padding (90% of width/height + 180px margins) to prevent clipping, especially for cursive fonts
            $letterSpacingRender = (strlen($text) - 1) * ($letterSpacing * $renderScale);
            $gdTextWidth = (int) (($baseTextWidth + $letterSpacingRender) * 1.9) + 180;
            $gdTextHeight = (int) ($baseTextHeight * 1.9) + 180;
            $gdTextCanvas = imagecreatetruecolor($gdTextWidth, $gdTextHeight);
            imagealphablending($gdTextCanvas, false);
            imagesavealpha($gdTextCanvas, true);
            $transparentGd = imagecolorallocatealpha($gdTextCanvas, 0, 0, 0, 127);
            imagefill($gdTextCanvas, 0, 0, $transparentGd);

            imagealphablending($gdTextCanvas, true);
            $textColorBase = imagecolorallocate($gdTextCanvas, $fontRgb['r'], $fontRgb['g'], $fontRgb['b']);
            // Center text in canvas with very generous padding
            $x = 90;
            $y = 90 + $baseTextHeight;
            imagettftext($gdTextCanvas, $renderFontSize, 0, $x, $y, $textColorBase, $fontPath, $text);
        } else {
            // Fallback to GD font
            $gdFontSize = 5; // Largest built-in font (13px)
            $gdBaseSize = 13;
            $scaleFactor = $fontSize / $gdBaseSize;

            $gdTextWidth = (int) (strlen($text) * 6 * $scaleFactor * $renderScale);
            $gdTextHeight = (int) ($gdBaseSize * $scaleFactor * $renderScale);
            $gdTextCanvas = imagecreatetruecolor($gdTextWidth, $gdTextHeight);
            imagealphablending($gdTextCanvas, false);
            imagesavealpha($gdTextCanvas, true);
            $transparentGd = imagecolorallocatealpha($gdTextCanvas, 0, 0, 0, 127);
            imagefill($gdTextCanvas, 0, 0, $transparentGd);

            // Draw at base size first
            $baseTextCanvas = imagecreatetruecolor((int) (strlen($text) * 6), $gdBaseSize);
            $textColorBase = imagecolorallocate($baseTextCanvas, $fontRgb['r'], $fontRgb['g'], $fontRgb['b']);
            imagestring($baseTextCanvas, $gdFontSize, 0, 0, $text, $textColorBase);

            // Scale to render size
            imagealphablending($gdTextCanvas, true);
            imagecopyresampled($gdTextCanvas, $baseTextCanvas, 0, 0, 0, 0, $gdTextWidth, $gdTextHeight, (int) (strlen($text) * 6), $gdBaseSize);
            imagedestroy($baseTextCanvas);
        }

        // Center text in canvas
        if ($fontPath && function_exists('imagettftext')) {
            // For TTF, text is already rendered at correct size
            // Extract only the text portion (excluding padding) when copying
            $textX = (int) (($textCanvasWidth - $gdTextWidth) / 2);
            $textY = (int) (($textCanvasHeight - $gdTextHeight) / 2);
            // Ensure we don't copy beyond canvas bounds
            $copyWidth = min($gdTextWidth, $textCanvasWidth - $textX);
            $copyHeight = min($gdTextHeight, $textCanvasHeight - $textY);
            if ($copyWidth > 0 && $copyHeight > 0) {
                imagealphablending($textCanvas, true);
                imagecopy($textCanvas, $gdTextCanvas, max(0, $textX), max(0, $textY), 0, 0, $copyWidth, $copyHeight);
            }
        } else {
            // For GD font, scale down from render size
            $textX = (int) (($textCanvasWidth - $gdTextWidth) / 2);
            $textY = (int) (($textCanvasHeight - $gdTextHeight) / 2);
            imagealphablending($textCanvas, true);
            imagecopy($textCanvas, $gdTextCanvas, $textX, $textY, 0, 0, $gdTextWidth, $gdTextHeight);
        }
        imagedestroy($gdTextCanvas);

        // Scale down to final size and composite onto watermark
        // Ensure we don't exceed canvas bounds
        $finalTextWidth = min((int) $textWidth, (int) $totalWidth);
        // Use actual calculated height instead of font size to prevent bottom clipping
        if ($fontPath && function_exists('imagettfbbox')) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $actualTextHeight = abs($bbox[7] - $bbox[1]);
            $finalTextHeight = min((int) ($actualTextHeight * 1.5), (int) $totalHeight);
        } else {
            $finalTextHeight = min((int) (($fontSize * $lineHeight) * 1.5), (int) $totalHeight);
        }
        $textX = max(0, (int) (($totalWidth - $finalTextWidth) / 2));
        $textY = max(0, (int) (($totalHeight - $finalTextHeight) / 2));
        imagealphablending($textImage, true);
        imagecopyresampled(
            $textImage, $textCanvas,
            $textX, $textY,
            0, 0,
            $finalTextWidth, $finalTextHeight,
            $textCanvasWidth, $textCanvasHeight
        );
        imagedestroy($textCanvas);

        // Get position for watermark - convert center coordinates to top-left
        $pos = $this->getWatermarkPosition($position, $imageWidth, $imageHeight, $totalWidth, $totalHeight);
        $x = (int) ($pos['x'] - ($totalWidth / 2));
        $y = (int) ($pos['y'] - ($totalHeight / 2));

        // Composite text image onto main image
        imagealphablending($gdImage, true);
        $this->imagecopymerge_alpha($gdImage, $textImage, $x, $y, 0, 0, (int) $totalWidth, (int) $totalHeight, (int) ($opacity * 100));

        imagedestroy($textImage);
    }

    /**
     * Apply image watermark using GD
     */
    protected function applyImageWatermarkGd(
        $gdImage,
        \App\Domains\Memora\Models\MemoraWatermark $watermark,
        int $imageWidth,
        int $imageHeight,
        float $opacity,
        string $position
    ): void {
        if (! $watermark->imageFile || ! $watermark->imageFile->url) {
            throw new \RuntimeException('Watermark image not found');
        }

        $watermarkUrl = $watermark->imageFile->url;

        try {
            $watermarkTempPath = $this->downloadImageToTemp($watermarkUrl);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to download watermark image: '.$e->getMessage());
        }

        if (! file_exists($watermarkTempPath)) {
            throw new \RuntimeException('Watermark image file not found after download');
        }

        // Check if it's SVG and convert to PNG
        $isSvg = str_ends_with(strtolower($watermarkTempPath), '.svg') ||
                 str_contains(strtolower($watermarkUrl), '.svg');

        $originalSvgPath = null;
        if ($isSvg) {
            try {
                $originalSvgPath = $watermarkTempPath;
                $watermarkTempPath = $this->convertSvgToPng($watermarkTempPath);

                if (! file_exists($watermarkTempPath)) {
                    throw new \RuntimeException('SVG conversion failed: PNG file not created');
                }
            } catch (\Exception $e) {
                if (file_exists($watermarkTempPath)) {
                    unlink($watermarkTempPath);
                }
                if ($originalSvgPath && file_exists($originalSvgPath)) {
                    unlink($originalSvgPath);
                }
                throw new \RuntimeException('Failed to convert SVG watermark: '.$e->getMessage());
            }
        }

        $watermarkInfo = @getimagesize($watermarkTempPath);
        if (! $watermarkInfo || ! isset($watermarkInfo['mime'])) {
            if (file_exists($watermarkTempPath)) {
                unlink($watermarkTempPath);
            }
            if ($originalSvgPath && file_exists($originalSvgPath)) {
                unlink($originalSvgPath);
            }
            throw new \RuntimeException('Invalid watermark image format. File exists but cannot be read as image. Path: '.$watermarkTempPath);
        }

        $watermarkMime = $watermarkInfo['mime'];
        $watermarkGd = match ($watermarkMime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($watermarkTempPath),
            'image/png' => imagecreatefrompng($watermarkTempPath),
            'image/gif' => imagecreatefromgif($watermarkTempPath),
            'image/webp' => imagecreatefromwebp($watermarkTempPath),
            default => null,
        };

        if (! $watermarkGd) {
            unlink($watermarkTempPath);
            if ($originalSvgPath && file_exists($originalSvgPath)) {
                unlink($originalSvgPath);
            }
            throw new \RuntimeException('Failed to load watermark image');
        }

        $watermarkOrigWidth = imagesx($watermarkGd);
        $watermarkOrigHeight = imagesy($watermarkGd);

        // Enhanced scaling: padding-aware with diagonal fallback for extreme aspect ratios
        $scalePercent = ($watermark->scale ?? 100) / 100; // Convert to 0.0-1.0

        // Calculate padding (5% of min dimension, minimum 20px)
        $basePadding = min($imageWidth, $imageHeight) * 0.05;
        $padding = max(20, (int) $basePadding);

        // Calculate usable dimensions (accounting for padding)
        $usableWidth = max($imageWidth - ($padding * 2), $imageWidth * 0.9);
        $usableHeight = max($imageHeight - ($padding * 2), $imageHeight * 0.9);
        $minImageDimension = min($usableWidth, $usableHeight);

        // For extreme aspect ratios (panoramic/tall), use diagonal as fallback
        $aspectRatio = $imageWidth / $imageHeight;
        $isExtremeAspectRatio = $aspectRatio > 3.0 || $aspectRatio < 0.33;

        if ($isExtremeAspectRatio) {
            // Use diagonal-based scaling for extreme aspect ratios
            $diagonal = sqrt($imageWidth * $imageWidth + $imageHeight * $imageHeight);
            $baseSize = $diagonal * 0.05; // 5% of diagonal
            $maxWatermarkSize = $baseSize * 5; // Max 25% of diagonal
            $targetWatermarkSize = $maxWatermarkSize * $scalePercent;
        } else {
            // Standard min-dimension approach
            $maxWatermarkSize = $minImageDimension * 0.25; // Max 25% of min dimension
            $targetWatermarkSize = $maxWatermarkSize * $scalePercent;
        }

        // Maintain watermark aspect ratio
        $watermarkAspectRatio = $watermarkOrigWidth / $watermarkOrigHeight;

        if ($watermarkOrigWidth > $watermarkOrigHeight) {
            $watermarkWidth = $targetWatermarkSize;
            $watermarkHeight = $targetWatermarkSize / $watermarkAspectRatio;
        } else {
            $watermarkHeight = $targetWatermarkSize;
            $watermarkWidth = $targetWatermarkSize * $watermarkAspectRatio;
        }

        // Ensure watermark doesn't exceed image bounds (90% max) and enforce minimum size
        $maxSize = $minImageDimension * 0.9;
        $minSize = 20; // Minimum 20px for image watermarks

        if ($watermarkWidth > $maxSize || $watermarkHeight > $maxSize) {
            if ($watermarkWidth > $watermarkHeight) {
                $watermarkWidth = $maxSize;
                $watermarkHeight = $maxSize / $watermarkAspectRatio;
            } else {
                $watermarkHeight = $maxSize;
                $watermarkWidth = $maxSize * $watermarkAspectRatio;
            }
        }

        // Enforce minimum size
        if ($watermarkWidth < $minSize || $watermarkHeight < $minSize) {
            if ($watermarkWidth < $watermarkHeight) {
                $watermarkWidth = $minSize;
                $watermarkHeight = $minSize / $watermarkAspectRatio;
            } else {
                $watermarkHeight = $minSize;
                $watermarkWidth = $minSize * $watermarkAspectRatio;
            }
        }

        // Resize watermark
        $watermarkResized = imagecreatetruecolor((int) $watermarkWidth, (int) $watermarkHeight);
        imagealphablending($watermarkResized, false);
        imagesavealpha($watermarkResized, true);
        imagecopyresampled($watermarkResized, $watermarkGd, 0, 0, 0, 0, (int) $watermarkWidth, (int) $watermarkHeight, $watermarkOrigWidth, $watermarkOrigHeight);

        $pos = $this->getWatermarkPosition($position, $imageWidth, $imageHeight, $watermarkWidth, $watermarkHeight);
        // Convert center coordinates to top-left
        $x = (int) ($pos['x'] - ($watermarkWidth / 2));
        $y = (int) ($pos['y'] - ($watermarkHeight / 2));

        // Apply opacity and composite
        imagealphablending($gdImage, true);
        $this->imagecopymerge_alpha($gdImage, $watermarkResized, $x, $y, 0, 0, (int) $watermarkWidth, (int) $watermarkHeight, (int) ($opacity * 100));

        imagedestroy($watermarkGd);
        imagedestroy($watermarkResized);
        unlink($watermarkTempPath);
        // Clean up original SVG if it was converted
        if ($originalSvgPath && file_exists($originalSvgPath)) {
            unlink($originalSvgPath);
        }
    }

    /**
     * Image copy merge with alpha support
     */
    protected function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct): void
    {
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
        imagedestroy($cut);
    }

    /**
     * Get watermark position coordinates
     */
    protected function getWatermarkPosition(string $position, int $imageWidth, int $imageHeight, float $watermarkWidth, float $watermarkHeight): array
    {
        // Match frontend getWatermarkPosition logic exactly
        // Frontend returns values that are used directly as top-left coordinates
        $positions = [
            'top-left' => ['x' => $watermarkWidth / 2, 'y' => $watermarkHeight / 2],
            'top' => ['x' => $imageWidth / 2, 'y' => $watermarkHeight / 2],
            'top-right' => ['x' => $imageWidth - $watermarkWidth / 2, 'y' => $watermarkHeight / 2],
            'left' => ['x' => $watermarkWidth / 2, 'y' => $imageHeight / 2],
            'center' => ['x' => $imageWidth / 2, 'y' => $imageHeight / 2],
            'right' => ['x' => $imageWidth - $watermarkWidth / 2, 'y' => $imageHeight / 2],
            'bottom-left' => ['x' => $watermarkWidth / 2, 'y' => $imageHeight - $watermarkHeight / 2],
            'bottom' => ['x' => $imageWidth / 2, 'y' => $imageHeight - $watermarkHeight / 2],
            'bottom-right' => ['x' => $imageWidth - $watermarkWidth / 2, 'y' => $imageHeight - $watermarkHeight / 2],
        ];

        $pos = $positions[$position] ?? $positions['center'];

        // Return as integers for GD
        return [
            'x' => (int) $pos['x'],
            'y' => (int) $pos['y'],
        ];
    }

    /**
     * Convert hex color to RGB
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Convert SVG to PNG for GD processing
     */
    protected function convertSvgToPng(string $svgPath): string
    {
        if (! file_exists($svgPath)) {
            throw new \RuntimeException('SVG file does not exist: '.$svgPath);
        }

        $pngPath = sys_get_temp_dir().'/'.uniqid('watermark_svg_', true).'.png';

        // Try Imagick first (best quality)
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick;
                $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                $imagick->setResolution(300, 300);
                $imagick->readImage($svgPath);
                $imagick->setImageFormat('png');
                $imagick->writeImage($pngPath);
                $imagick->clear();
                $imagick->destroy();

                if (file_exists($pngPath) && filesize($pngPath) > 0) {
                    \Illuminate\Support\Facades\Log::info('SVG converted to PNG using Imagick', [
                        'svg_path' => $svgPath,
                        'png_path' => $pngPath,
                        'png_size' => filesize($pngPath),
                    ]);

                    return $pngPath;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Imagick SVG conversion failed', [
                    'error' => $e->getMessage(),
                    'svg_path' => $svgPath,
                ]);
            }
        }

        // Fallback: Use Inkscape if available (common on Linux)
        $inkscapePath = trim(shell_exec('which inkscape') ?: '');
        if ($inkscapePath && file_exists($inkscapePath)) {
            $command = escapeshellarg($inkscapePath).' --export-type=png --export-filename='.escapeshellarg($pngPath).' '.escapeshellarg($svgPath).' 2>&1';
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($pngPath) && filesize($pngPath) > 0) {
                \Illuminate\Support\Facades\Log::info('SVG converted to PNG using Inkscape', [
                    'svg_path' => $svgPath,
                    'png_path' => $pngPath,
                ]);

                return $pngPath;
            }
        }

        // Fallback: Use rsvg-convert if available
        $rsvgPath = trim(shell_exec('which rsvg-convert') ?: '');
        if ($rsvgPath && file_exists($rsvgPath)) {
            $command = escapeshellarg($rsvgPath).' -o '.escapeshellarg($pngPath).' '.escapeshellarg($svgPath).' 2>&1';
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($pngPath) && filesize($pngPath) > 0) {
                \Illuminate\Support\Facades\Log::info('SVG converted to PNG using rsvg-convert', [
                    'svg_path' => $svgPath,
                    'png_path' => $pngPath,
                ]);

                return $pngPath;
            }
        }

        // Last resort: Try to read SVG as XML and create a simple PNG placeholder
        // This is a basic fallback - SVG support in GD is limited
        $svgContent = file_get_contents($svgPath);
        if ($svgContent === false) {
            throw new \RuntimeException('Failed to read SVG file: '.$svgPath);
        }

        // Create a simple transparent PNG as fallback
        // Note: This won't render the SVG properly, but at least won't crash
        $fallbackPng = imagecreatetruecolor(100, 100);
        imagealphablending($fallbackPng, false);
        imagesavealpha($fallbackPng, true);
        $transparent = imagecolorallocatealpha($fallbackPng, 0, 0, 0, 127);
        imagefill($fallbackPng, 0, 0, $transparent);
        imagepng($fallbackPng, $pngPath);
        imagedestroy($fallbackPng);

        // Log warning that SVG conversion is not ideal
        \Illuminate\Support\Facades\Log::warning('SVG watermark converted using fallback method. Install Imagick for better SVG support.', [
            'svg_path' => $svgPath,
            'png_path' => $pngPath,
        ]);

        return $pngPath;
    }

    /**
     * Apply watermark in preview style (full-width background, centered text)
     * Similar to preview variant but using watermark's text and settings
     *
     * @param  string  $imagePath  Path to the image file
     * @param  \App\Domains\Memora\Models\MemoraWatermark  $watermark  Watermark to apply
     * @param  int|null  $mediaWidth  Optional: pre-calculated media width (if null, will be calculated from file)
     * @param  int|null  $mediaHeight  Optional: pre-calculated media height (if null, will be calculated from file)
     */
    protected function applyWatermarkInPreviewStyle(string $imagePath, \App\Domains\Memora\Models\MemoraWatermark $watermark, ?int $mediaWidth = null, ?int $mediaHeight = null): string
    {
        // This method only handles text watermarks - image watermarks should use applyWatermarkToImage
        $watermarkType = $watermark->type instanceof \App\Domains\Memora\Enums\WatermarkTypeEnum
            ? $watermark->type
            : \App\Domains\Memora\Enums\WatermarkTypeEnum::from($watermark->type ?? 'text');

        if ($watermarkType !== \App\Domains\Memora\Enums\WatermarkTypeEnum::TEXT) {
            throw new \RuntimeException('Preview style watermark only supports text watermarks');
        }

        $outputPath = sys_get_temp_dir().'/'.uniqid('watermarked_preview_', true).'.jpg';

        // Load image using GD
        $imageInfo = getimagesize($imagePath);
        if (! $imageInfo) {
            throw new \RuntimeException('Invalid image file');
        }

        $mimeType = $imageInfo['mime'];

        $gdImage = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($imagePath),
            'image/png' => imagecreatefrompng($imagePath),
            'image/gif' => imagecreatefromgif($imagePath),
            'image/webp' => imagecreatefromwebp($imagePath),
            default => throw new \RuntimeException('Unsupported image type: '.$mimeType),
        };

        if (! $gdImage) {
            throw new \RuntimeException('Failed to create GD image');
        }

        // Use provided dimensions or get actual GD image dimensions for watermark positioning
        $imageWidth = $mediaWidth ?? imagesx($gdImage);
        $imageHeight = $mediaHeight ?? imagesy($gdImage);

        // Get watermark text
        $text = $watermark->text ?? '';
        if (empty($text)) {
            throw new \RuntimeException('Watermark text is required');
        }

        // Apply text transform
        if ($watermark->text_transform instanceof \App\Domains\Memora\Enums\TextTransformEnum) {
            $transform = $watermark->text_transform;
            if ($transform === \App\Domains\Memora\Enums\TextTransformEnum::UPPERCASE) {
                $text = strtoupper($text);
            } elseif ($transform === \App\Domains\Memora\Enums\TextTransformEnum::LOWERCASE) {
                $text = strtolower($text);
            }
        }

        // Split text into lines (split by newline or use single line)
        $lines = ! empty($watermark->text) ? explode("\n", $text) : [$text];
        $lines = array_filter($lines, fn ($line) => ! empty(trim($line)));

        if (empty($lines)) {
            throw new \RuntimeException('Watermark text is empty');
        }

        $minDimension = min($imageWidth, $imageHeight);

        // Find font - use watermark's font_family if specified
        $fontFamily = $watermark->font_family;
        if ($fontFamily) {
            \Log::debug('Watermark font_family lookup (standard)', [
                'watermark_uuid' => $watermark->uuid,
                'font_family' => $fontFamily,
                'font_family_type' => gettype($fontFamily),
            ]);
        }
        $fontPath = $this->findSystemFont($fontFamily);
        if ($fontPath && $fontFamily) {
            \Log::debug('Font found (standard)', ['font_family' => $fontFamily, 'font_path' => $fontPath]);
        } elseif ($fontFamily) {
            \Log::warning('Font not found (standard)', ['font_family' => $fontFamily]);
        }

        // Calculate font sizes - use watermark scale or default
        $scalePercent = ($watermark->scale ?? 50) / 100;
        $baseFontSize = max((int) ($minDimension * 0.06 * $scalePercent), 30);

        // Calculate actual text widths and scale if needed
        $maxAvailableWidth = $imageWidth * 0.9;
        $maxTextWidth = 0;

        if ($fontPath && function_exists('imagettfbbox')) {
            foreach ($lines as $line) {
                $bbox = imagettfbbox($baseFontSize, 0, $fontPath, $line);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $maxTextWidth = max($maxTextWidth, $textWidth);
            }
        } else {
            foreach ($lines as $line) {
                $textWidth = strlen($line) * ($baseFontSize * 0.6);
                $maxTextWidth = max($maxTextWidth, $textWidth);
            }
        }

        // Scale down if needed
        if ($maxTextWidth > $maxAvailableWidth) {
            $scaleFactor = $maxAvailableWidth / $maxTextWidth;
            $baseFontSize = (int) ($baseFontSize * $scaleFactor);
            $maxTextWidth = $maxAvailableWidth;
        }

        // Calculate total height - use watermark's line_height if available
        $lineHeight = $watermark->line_height ?? 1.2;
        $lineSpacing = (int) ($baseFontSize * ($lineHeight - 1));
        $totalHeight = (count($lines) * $baseFontSize) + ((count($lines) - 1) * $lineSpacing);
        // Use watermark's padding if available, otherwise calculate from font size
        $padding = $watermark->padding ?? (int) ($baseFontSize * 0.4);

        $textWidth = (int) ($maxTextWidth + ($padding * 2));
        $textHeight = (int) ($totalHeight + ($padding * 2));

        // Create watermark canvas (sized to text)
        $watermarkCanvas = imagecreatetruecolor($textWidth, $textHeight);
        imagealphablending($watermarkCanvas, false);
        imagesavealpha($watermarkCanvas, true);
        $transparent = imagecolorallocatealpha($watermarkCanvas, 0, 0, 0, 127);
        imagefill($watermarkCanvas, 0, 0, $transparent);

        // Draw background
        imagealphablending($watermarkCanvas, true);
        $opacity = ($watermark->opacity ?? 80) / 100;
        $bgAlpha = (int) ((1 - $opacity) * 127);
        $borderRadius = $watermark->border_radius ?? 0;

        $bgColor = $watermark->background_color ?? null;
        if ($bgColor) {
            $bgRgb = $this->hexToRgb($bgColor);
            $bgColorRes = imagecolorallocatealpha($watermarkCanvas, $bgRgb['r'], $bgRgb['g'], $bgRgb['b'], $bgAlpha);

            if ($borderRadius > 0) {
                // Draw rounded rectangle (simplified)
                imagefilledrectangle($watermarkCanvas, $borderRadius, 0, $textWidth - $borderRadius - 1, $textHeight - 1, $bgColorRes);
                imagefilledrectangle($watermarkCanvas, 0, $borderRadius, $textWidth - 1, $textHeight - $borderRadius - 1, $bgColorRes);
            } else {
                imagefilledrectangle($watermarkCanvas, 0, 0, $textWidth - 1, $textHeight - 1, $bgColorRes);
            }
        }

        // Draw border if specified (even without background)
        if (($watermark->border_width ?? 0) > 0 && $watermark->border_color) {
            $borderRgb = $this->hexToRgb($watermark->border_color);
            $borderColorRes = imagecolorallocatealpha($watermarkCanvas, $borderRgb['r'], $borderRgb['g'], $borderRgb['b'], $bgAlpha);
            $borderWidth = $watermark->border_width;

            if ($borderRadius > 0) {
                // Draw rounded border
                for ($i = 0; $i < $borderWidth; $i++) {
                    $x = $i;
                    $y = $i;
                    $w = $textWidth - 1 - ($i * 2);
                    $h = $textHeight - 1 - ($i * 2);
                    $r = max(0, $borderRadius - $i);

                    // Simplified rounded rectangle border
                    if ($r > 0) {
                        // Top and bottom edges
                        imageline($watermarkCanvas, $x + $r, $y, $x + $w - $r, $y, $borderColorRes);
                        imageline($watermarkCanvas, $x + $r, $y + $h, $x + $w - $r, $y + $h, $borderColorRes);
                        // Left and right edges
                        imageline($watermarkCanvas, $x, $y + $r, $x, $y + $h - $r, $borderColorRes);
                        imageline($watermarkCanvas, $x + $w, $y + $r, $x + $w, $y + $h - $r, $borderColorRes);
                        // Corners (simplified)
                        imagearc($watermarkCanvas, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $borderColorRes);
                        imagearc($watermarkCanvas, $x + $w - $r, $y + $r, $r * 2, $r * 2, 270, 360, $borderColorRes);
                        imagearc($watermarkCanvas, $x + $r, $y + $h - $r, $r * 2, $r * 2, 90, 180, $borderColorRes);
                        imagearc($watermarkCanvas, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, 0, 90, $borderColorRes);
                    } else {
                        imagerectangle($watermarkCanvas, $x, $y, $x + $w, $y + $h, $borderColorRes);
                    }
                }
            } else {
                // Draw rectangular border
                for ($i = 0; $i < $borderWidth; $i++) {
                    imagerectangle($watermarkCanvas, $i, $i, $textWidth - 1 - $i, $textHeight - 1 - $i, $borderColorRes);
                }
            }
        }

        // Draw text lines
        $yOffset = $padding;
        $fontColor = $watermark->font_color ?? '#FFFFFF';
        $fontRgb = $this->hexToRgb($fontColor);

        foreach ($lines as $line) {
            if ($fontPath && function_exists('imagettftext')) {
                $renderScale = 3;
                $renderSize = $baseFontSize * $renderScale;

                $bbox = imagettfbbox($renderSize, 0, $fontPath, $line);
                $textWidthActual = abs($bbox[4] - $bbox[0]);
                $textHeightActual = abs($bbox[7] - $bbox[1]);

                $lineRenderCanvas = imagecreatetruecolor($textWidthActual + 20, $textHeightActual + 20);
                imagealphablending($lineRenderCanvas, false);
                imagesavealpha($lineRenderCanvas, true);
                $transparentLine = imagecolorallocatealpha($lineRenderCanvas, 0, 0, 0, 127);
                imagefill($lineRenderCanvas, 0, 0, $transparentLine);

                imagealphablending($lineRenderCanvas, true);
                $textColor = imagecolorallocate($lineRenderCanvas, $fontRgb['r'], $fontRgb['g'], $fontRgb['b']);
                $x = 10;
                $y = 10 + $textHeightActual;

                imagettftext($lineRenderCanvas, $renderSize, 0, $x, $y, $textColor, $fontPath, $line);

                $finalWidth = (int) ($textWidthActual / $renderScale);
                $finalHeight = (int) ($textHeightActual / $renderScale);
                $lineCanvas = imagecreatetruecolor($finalWidth, $finalHeight);
                imagealphablending($lineCanvas, false);
                imagesavealpha($lineCanvas, true);
                $transparentFinal = imagecolorallocatealpha($lineCanvas, 0, 0, 0, 127);
                imagefill($lineCanvas, 0, 0, $transparentFinal);

                imagealphablending($lineCanvas, true);
                imagecopyresampled(
                    $lineCanvas, $lineRenderCanvas,
                    0, 0, 10, 10,
                    $finalWidth, $finalHeight,
                    $textWidthActual, $textHeightActual
                );
                imagedestroy($lineRenderCanvas);

                $xOffset = (int) (($textWidth - $finalWidth) / 2);
                imagecopy($watermarkCanvas, $lineCanvas, $xOffset, $yOffset, 0, 0, $finalWidth, $finalHeight);
                imagedestroy($lineCanvas);

                $yOffset += $finalHeight + $lineSpacing;
            } else {
                // Fallback to GD font
                $lineWidth = (int) (strlen($line) * ($baseFontSize * 0.6));
                $lineCanvas = imagecreatetruecolor($lineWidth, $baseFontSize);
                imagealphablending($lineCanvas, false);
                imagesavealpha($lineCanvas, true);
                $transparentLine = imagecolorallocatealpha($lineCanvas, 0, 0, 0, 127);
                imagefill($lineCanvas, 0, 0, $transparentLine);

                $renderScale = 3;
                $renderWidth = $lineWidth * $renderScale;
                $renderHeight = $baseFontSize * $renderScale;
                $renderCanvas = imagecreatetruecolor($renderWidth, $renderHeight);

                $gdFontSize = 5;
                $gdBaseSize = 13;
                $baseWidth = strlen($line) * 6;
                $baseCanvas = imagecreatetruecolor($baseWidth, $gdBaseSize);
                $textColor = imagecolorallocate($baseCanvas, $fontRgb['r'], $fontRgb['g'], $fontRgb['b']);
                imagestring($baseCanvas, $gdFontSize, 0, 0, $line, $textColor);

                imagecopyresampled($renderCanvas, $baseCanvas, 0, 0, 0, 0, $renderWidth, $renderHeight, $baseWidth, $gdBaseSize);
                imagedestroy($baseCanvas);

                imagealphablending($lineCanvas, true);
                imagecopyresampled($lineCanvas, $renderCanvas, 0, 0, 0, 0, $lineWidth, $baseFontSize, $renderWidth, $renderHeight);
                imagedestroy($renderCanvas);

                $xOffset = (int) (($textWidth - $lineWidth) / 2);
                imagecopy($watermarkCanvas, $lineCanvas, $xOffset, $yOffset, 0, 0, $lineWidth, $baseFontSize);
                imagedestroy($lineCanvas);

                $yOffset += $baseFontSize + $lineSpacing;
            }
        }

        // Composite watermark onto main image - use watermark's position
        // For preview style, position is always center (full-width banner)
        $position = $watermark->position instanceof \App\Domains\Memora\Enums\WatermarkPositionEnum
            ? $watermark->position->value
            : ($watermark->position ?? 'center');

        // Calculate top-left coordinates from center position
        $pos = $this->getWatermarkPosition($position, $imageWidth, $imageHeight, $textWidth, $textHeight);
        // getWatermarkPosition returns center coordinates, convert to top-left
        $watermarkX = (int) ($pos['x'] - ($textWidth / 2));
        $watermarkY = (int) ($pos['y'] - ($textHeight / 2));
        imagealphablending($gdImage, true);
        imagecopy($gdImage, $watermarkCanvas, $watermarkX, $watermarkY, 0, 0, $textWidth, $textHeight);
        imagedestroy($watermarkCanvas);

        // Save as JPEG
        $quality = random_int(40, 50); // Same quality as medium/large variants
        imagejpeg($gdImage, $outputPath, $quality);
        imagedestroy($gdImage);

        return $outputPath;
    }

    /**
     * Find a system TTF font for smooth text rendering
     */
    protected function findSystemFont(?string $fontFamily = null): ?string
    {
        if (! function_exists('imagettftext')) {
            return null;
        }

        // If font family is specified, try to find it
        if ($fontFamily) {
            // Extract font name from CSS font-family string (e.g., "'Playfair Display', sans-serif" -> "Playfair Display")
            $originalFontFamily = $fontFamily;
            $fontFamily = trim($fontFamily);
            // Remove quotes and extract first font name (before comma)
            $fontFamilyParts = explode(',', $fontFamily);
            $fontFamily = trim($fontFamilyParts[0]);
            // Remove surrounding quotes if present (handle both single and double quotes)
            $fontFamily = trim($fontFamily, " \t\n\r\0\x0B'\"");

            // Normalize font family name - handle various formats
            $fontName = str_replace([' ', '-', '_'], '', $fontFamily);
            $fontNameLower = strtolower($fontName);

            \Log::debug('Font extraction', [
                'original' => $originalFontFamily,
                'extracted' => $fontFamily,
                'normalized' => $fontNameLower,
            ]);

            $bundledFonts = [
                // Display & Bold
                'bebasneue' => 'BebasNeue-Regular.ttf',
                'bebas' => 'BebasNeue-Regular.ttf',
                'oswald' => 'Oswald-Regular.ttf',
                'abrilfatface' => 'AbrilFatface-Regular.ttf',
                'abril' => 'AbrilFatface-Regular.ttf',
                'bungee' => 'Bungee-Regular.ttf',
                'righteous' => 'Righteous-Regular.ttf',
                // Modern Sans
                'playfairdisplay' => 'PlayfairDisplay-Regular.ttf',
                'playfair' => 'PlayfairDisplay-Regular.ttf',
                'montserrat' => 'Montserrat-Regular.ttf',
                'lato' => 'Lato-Regular.ttf',
                'raleway' => 'Raleway-Regular.ttf',
                'opensans' => 'OpenSans-Regular.ttf',
                'opensan' => 'OpenSans-Regular.ttf',
                'roboto' => 'Roboto-Regular.ttf',
                'poppins' => 'Poppins-Regular.ttf',
                'inter' => 'Inter-Regular.ttf',
                'nunito' => 'Nunito-Regular.ttf',
                'barlow' => 'Barlow-Regular.ttf',
                'worksans' => 'WorkSans-Regular.ttf',
                'worksan' => 'WorkSans-Regular.ttf',
                'spacegrotesk' => 'SpaceGrotesk-Regular.ttf',
                'spacegrotes' => 'SpaceGrotesk-Regular.ttf',
                'outfit' => 'Outfit-Regular.ttf',
                'dmsans' => 'DMSans-Regular.ttf',
                'dmsan' => 'DMSans-Regular.ttf',
                'plusjakartasans' => 'PlusJakartaSans-Regular.ttf',
                'plusjakarta' => 'PlusJakartaSans-Regular.ttf',
                'manrope' => 'Manrope-Regular.ttf',
                'sora' => 'Sora-Regular.ttf',
                'figtree' => 'Figtree-Regular.ttf',
                'syne' => 'Syne-Regular.ttf',
                'sourcesanspro' => 'SourceSansPro-Regular.ttf',
                'source' => 'SourceSansPro-Regular.ttf',
                'ubuntu' => 'Ubuntu-Regular.ttf',
                // Serif
                'merriweather' => 'Merriweather-Regular.ttf',
                'crimsontext' => 'CrimsonText-Regular.ttf',
                'crimson' => 'CrimsonText-Regular.ttf',
                'lora' => 'Lora-Regular.ttf',
                // Monospace
                'spacemono' => 'SpaceMono-Regular.ttf',
                'spacemon' => 'SpaceMono-Regular.ttf',
                'jetbrainsmono' => 'JetBrainsMono-Regular.ttf',
                'jetbrains' => 'JetBrainsMono-Regular.ttf',
                // Sans-serif (rounded)
                'comfortaa' => 'Comfortaa-Regular.ttf',
                'quicksand' => 'Quicksand-Regular.ttf',
                'rubik' => 'Rubik-Regular.ttf',
                // Cursive/Script
                'dancingscript' => 'DancingScript-Regular.ttf',
                'dancing' => 'DancingScript-Regular.ttf',
                'pacifico' => 'Pacifico-Regular.ttf',
                'caveat' => 'Caveat-Regular.ttf',
                'kalam' => 'Kalam-Regular.ttf',
                'satisfy' => 'Satisfy-Regular.ttf',
                'greatvibes' => 'GreatVibes-Regular.ttf',
                'greatvibe' => 'GreatVibes-Regular.ttf',
                'amaticsc' => 'AmaticSC-Regular.ttf',
                'amatic' => 'AmaticSC-Regular.ttf',
                'shadowsintolight' => 'ShadowsIntoLight-Regular.ttf',
                'shadows' => 'ShadowsIntoLight-Regular.ttf',
                'permanentmarker' => 'PermanentMarker-Regular.ttf',
                'permanent' => 'PermanentMarker-Regular.ttf',
                'indieflower' => 'IndieFlower-Regular.ttf',
                'indie' => 'IndieFlower-Regular.ttf',
            ];

            if (isset($bundledFonts[$fontNameLower])) {
                $fontPath = resource_path('fonts/'.$bundledFonts[$fontNameLower]);
                if (file_exists($fontPath) && is_readable($fontPath)) {
                    \Log::debug('Font found in bundled map', [
                        'key' => $fontNameLower,
                        'file' => $bundledFonts[$fontNameLower],
                        'path' => $fontPath,
                    ]);

                    return $fontPath;
                } else {
                    \Log::warning('Font file not found', [
                        'key' => $fontNameLower,
                        'expected_file' => $bundledFonts[$fontNameLower],
                        'path' => $fontPath,
                        'exists' => file_exists($fontPath),
                    ]);
                }
            } else {
                \Log::debug('Font not in bundled map', [
                    'normalized' => $fontNameLower,
                    'available_keys' => array_keys($bundledFonts),
                ]);
            }

            // Try multiple direct filename formats
            $directFormats = [
                str_replace(' ', '', ucwords(strtolower($fontFamily))).'-Regular.ttf',
                str_replace(' ', '-', ucwords(strtolower($fontFamily))).'-Regular.ttf',
                ucwords(strtolower($fontFamily)).'-Regular.ttf',
                str_replace(' ', '', $fontFamily).'-Regular.ttf',
                $fontFamily.'-Regular.ttf',
            ];

            foreach ($directFormats as $directName) {
                $directPath = resource_path('fonts/'.$directName);
                if (file_exists($directPath) && is_readable($directPath)) {
                    return $directPath;
                }
            }

            // Try common system font paths for the specified font
            $fontVariants = [
                $fontFamily,
                str_replace(' ', '', $fontFamily),
                str_replace(' ', '-', $fontFamily),
            ];

            $systemPaths = [
                '/System/Library/Fonts/Supplemental/',
                '/Library/Fonts/',
                '/usr/share/fonts/truetype/',
                '/usr/share/fonts/truetype/dejavu/',
                '/usr/share/fonts/truetype/liberation/',
                'C:/Windows/Fonts/',
            ];

            foreach ($fontVariants as $variant) {
                foreach ($systemPaths as $basePath) {
                    $paths = [
                        $basePath.$variant.'.ttf',
                        $basePath.$variant.'-Regular.ttf',
                        $basePath.$variant.'-Bold.ttf',
                        $basePath.ucfirst($variant).'.ttf',
                        $basePath.ucfirst($variant).'-Regular.ttf',
                    ];

                    foreach ($paths as $path) {
                        if (file_exists($path) && is_readable($path)) {
                            return $path;
                        }
                    }
                }
            }
        }

        // Fallback: Try bundled Google Font first
        $googleFontPath = resource_path('fonts/BebasNeue-Regular.ttf');
        if (file_exists($googleFontPath) && is_readable($googleFontPath)) {
            return $googleFontPath;
        }

        // Try system fonts
        $fontPaths = [
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
        ];

        foreach ($fontPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Apply watermark to video (both thumbnail and video file)
     */
    protected function applyWatermarkToVideo(
        MemoraMedia $media,
        \App\Models\UserFile $originalFile,
        \App\Domains\Memora\Models\MemoraWatermark $watermark,
        string $watermarkUuid,
        string $userId,
        bool $previewStyle,
        ?string $oldWatermarkedFileUuid
    ): void {
        $originalUrl = $originalFile->url;
        $videoTempPath = $this->downloadVideoToTemp($originalUrl);

        try {
            // 1. Watermark video thumbnail
            $thumbnailGenerator = app(\App\Services\Video\VideoThumbnailGenerator::class);
            $thumbnailPath = $thumbnailGenerator->generateThumbnail(
                new \Illuminate\Http\UploadedFile($videoTempPath, basename($videoTempPath), mime_content_type($videoTempPath), null, true)
            );

            if ($thumbnailPath) {
                // Apply watermark to thumbnail
                $watermarkType = $watermark->type instanceof \App\Domains\Memora\Enums\WatermarkTypeEnum
                    ? $watermark->type
                    : \App\Domains\Memora\Enums\WatermarkTypeEnum::from($watermark->type ?? 'text');

                $usePreviewStyle = $previewStyle && $watermarkType === \App\Domains\Memora\Enums\WatermarkTypeEnum::TEXT;

                $watermarkedThumbnailPath = $usePreviewStyle
                    ? $this->applyWatermarkInPreviewStyle($thumbnailPath, $watermark)
                    : $this->applyWatermarkToImage($thumbnailPath, $watermark);

                // Upload watermarked thumbnail
                $uploadService = app(\App\Services\Image\ImageUploadService::class);
                $thumbnailFile = new \Illuminate\Http\UploadedFile(
                    $watermarkedThumbnailPath,
                    basename($watermarkedThumbnailPath),
                    mime_content_type($watermarkedThumbnailPath),
                    null,
                    true
                );

                $thumbnailUploadResult = $uploadService->uploadImage($thumbnailFile, [
                    'context' => 'watermarked-thumbnail',
                    'visibility' => 'public',
                ]);

                $watermarkedThumbnailUrl = $thumbnailUploadResult['variants']['original'] ?? $thumbnailUploadResult['variants']['medium'] ?? '';
                @unlink($watermarkedThumbnailPath);
                @unlink($thumbnailPath);
            }

            // 2. Apply watermark to video using FFmpeg
            $watermarkedVideoPath = $this->applyWatermarkToVideoFile($videoTempPath, $watermark, $previewStyle);

            // Upload watermarked video
            $videoFile = new \Illuminate\Http\UploadedFile(
                $watermarkedVideoPath,
                basename($watermarkedVideoPath),
                mime_content_type($watermarkedVideoPath),
                null,
                true
            );

            $uploadService = app(\App\Services\Upload\UploadService::class);
            $videoUploadResult = $uploadService->upload($videoFile, [
                'context' => 'watermarked-video',
                'visibility' => 'public',
            ]);

            // Get video dimensions
            $videoThumbnailGen = app(\App\Services\Video\VideoThumbnailGenerator::class);
            $videoDims = $videoThumbnailGen->getVideoDimensions($watermarkedVideoPath);

            // Create UserFile for watermarked video
            $watermarkedFile = \App\Models\UserFile::create([
                'user_uuid' => $userId,
                'url' => $videoUploadResult->url,
                'path' => $videoUploadResult->path,
                'type' => 'video',
                'filename' => $originalFile->filename,
                'mime_type' => $videoUploadResult->mimeType,
                'size' => $videoUploadResult->size,
                'width' => $videoDims['width'] ?? $videoUploadResult->width,
                'height' => $videoDims['height'] ?? $videoUploadResult->height,
                'metadata' => [
                    'provider' => $videoUploadResult->provider,
                    'checksum' => $videoUploadResult->checksum,
                    'thumbnail' => $watermarkedThumbnailUrl ?? null,
                    'total_size_with_variants' => $videoUploadResult->size,
                ],
            ]);

            // Update cached storage
            $storageService = app(\App\Services\Storage\UserStorageService::class);
            $storageService->incrementStorage($userId, $videoUploadResult->size);

            // Update media with new watermarked file
            $media->update([
                'user_file_uuid' => $watermarkedFile->uuid,
                'watermark_uuid' => $watermarkUuid,
                'original_file_uuid' => $originalFile->uuid,
            ]);

            // Delete old watermarked file if it exists
            if ($oldWatermarkedFileUuid) {
                try {
                    $oldWatermarkedFile = \App\Models\UserFile::where('uuid', $oldWatermarkedFileUuid)->first();
                    if ($oldWatermarkedFile) {
                        // Delete from storage using UploadService
                        $uploadService = app(\App\Services\Upload\UploadService::class);
                        if (method_exists($uploadService, 'delete')) {
                            $variants = $oldWatermarkedFile->metadata['variants'] ?? [];
                            $uploadService->delete($oldWatermarkedFile->path ?? '', $variants);
                        }
                        $oldWatermarkedFile->delete();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old watermarked video file', [
                        'file_uuid' => $oldWatermarkedFileUuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Refresh media to return updated model
            $media->refresh();

            // Cleanup temp files
            @unlink($watermarkedVideoPath);
            @unlink($videoTempPath);
        } catch (\Exception $e) {
            // Cleanup on error
            @unlink($videoTempPath);
            throw $e;
        }
    }

    /**
     * Download video file to temporary location
     */
    protected function downloadVideoToTemp(string $url): string
    {
        $tempPath = sys_get_temp_dir().'/'.uniqid('video_', true);

        // Check if URL is a storage path
        if (str_starts_with($url, 'storage://') || (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://'))) {
            $path = str_replace('storage://', '', $url);
            $disksToCheck = ['public', 'local', 's3', 'r2'];

            foreach ($disksToCheck as $disk) {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($path)) {
                        $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($path);
                        if ($content !== false) {
                            // Detect video extension from path or mime type
                            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'mp4';
                            $tempPath .= '.'.$extension;
                            file_put_contents($tempPath, $content);

                            return $tempPath;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // Download from URL
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $content = @file_get_contents($url);
            if ($content !== false) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
                $tempPath .= '.'.$extension;
                file_put_contents($tempPath, $content);

                return $tempPath;
            }
        }

        throw new \RuntimeException('Failed to download video file');
    }

    /**
     * Apply watermark to video file using FFmpeg
     */
    protected function applyWatermarkToVideoFile(string $videoPath, \App\Domains\Memora\Models\MemoraWatermark $watermark, bool $previewStyle): string
    {
        if (! $this->isFFmpegAvailable()) {
            throw new \RuntimeException('FFmpeg is not available for video watermarking');
        }

        $outputPath = sys_get_temp_dir().'/'.uniqid('watermarked_video_', true).'.mp4';

        // Create watermark image overlay
        $watermarkImagePath = $this->createWatermarkOverlayImage($videoPath, $watermark, $previewStyle);

        try {
            // Get video dimensions
            $videoThumbnailGen = app(\App\Services\Video\VideoThumbnailGenerator::class);
            $dims = $videoThumbnailGen->getVideoDimensions($videoPath);
            $videoWidth = $dims['width'] ?? 1920;
            $videoHeight = $dims['height'] ?? 1080;

            // Calculate watermark position and size
            $watermarkInfo = getimagesize($watermarkImagePath);
            $watermarkWidth = $watermarkInfo[0];
            $watermarkHeight = $watermarkInfo[1];

            // Scale watermark using watermark's scale property (max 20% of video width)
            $watermarkScale = ($watermark->scale ?? 50) / 100;
            $maxWatermarkWidth = (int) ($videoWidth * 0.2 * $watermarkScale);
            $scale = min(1.0, $maxWatermarkWidth / $watermarkWidth);
            $scaledWidth = (int) ($watermarkWidth * $scale);
            $scaledHeight = (int) ($watermarkHeight * $scale);

            // Position: use watermark's position property
            $position = $watermark->position instanceof \App\Domains\Memora\Enums\WatermarkPositionEnum
                ? $watermark->position->value
                : ($watermark->position ?? 'center');

            $padding = 20;
            $x = (int) (($videoWidth - $scaledWidth) / 2);
            $y = (int) (($videoHeight - $scaledHeight) / 2);

            if ($position === 'bottom-right') {
                $x = $videoWidth - $scaledWidth - $padding;
                $y = $videoHeight - $scaledHeight - $padding;
            } elseif ($position === 'bottom-left') {
                $x = $padding;
                $y = $videoHeight - $scaledHeight - $padding;
            } elseif ($position === 'top-right') {
                $x = $videoWidth - $scaledWidth - $padding;
                $y = $padding;
            } elseif ($position === 'top-left') {
                $x = $padding;
                $y = $padding;
            } elseif ($position === 'center') {
                // Already set above
            }

            // FFmpeg command to overlay watermark on video
            $command = sprintf(
                'ffmpeg -i %s -i %s -filter_complex "[1:v]scale=%d:%d[wm];[0:v][wm]overlay=%d:%d" -c:a copy -c:v libx264 -preset medium -crf 23 %s 2>&1',
                escapeshellarg($videoPath),
                escapeshellarg($watermarkImagePath),
                $scaledWidth,
                $scaledHeight,
                $x,
                $y,
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            @unlink($watermarkImagePath);

            if ($returnCode !== 0 || ! file_exists($outputPath)) {
                throw new \RuntimeException('Failed to apply watermark to video. FFmpeg output: '.implode("\n", $output));
            }

            return $outputPath;
        } catch (\Exception $e) {
            @unlink($watermarkImagePath);
            throw $e;
        }
    }

    /**
     * Create watermark overlay image for video
     */
    protected function createWatermarkOverlayImage(string $videoPath, \App\Domains\Memora\Models\MemoraWatermark $watermark, bool $previewStyle): string
    {
        // Get video dimensions to size watermark appropriately
        $videoThumbnailGen = app(\App\Services\Video\VideoThumbnailGenerator::class);
        $dims = $videoThumbnailGen->getVideoDimensions($videoPath);
        $videoWidth = $dims['width'] ?? 1920;
        $videoHeight = $dims['height'] ?? 1080;

        $watermarkType = $watermark->type instanceof \App\Domains\Memora\Enums\WatermarkTypeEnum
            ? $watermark->type
            : \App\Domains\Memora\Enums\WatermarkTypeEnum::from($watermark->type ?? 'text');

        if ($watermarkType === \App\Domains\Memora\Enums\WatermarkTypeEnum::TEXT) {
            // For text watermark, create a temporary image and apply watermark to it
            $tempImagePath = sys_get_temp_dir().'/'.uniqid('temp_video_', true).'.jpg';
            $tempImage = imagecreatetruecolor($videoWidth, $videoHeight);
            imagefill($tempImage, 0, 0, imagecolorallocate($tempImage, 255, 255, 255));
            imagejpeg($tempImage, $tempImagePath, 100);
            imagedestroy($tempImage);

            // Apply watermark to temp image
            $usePreviewStyle = $previewStyle;
            $watermarkedPath = $usePreviewStyle
                ? $this->applyWatermarkInPreviewStyle($tempImagePath, $watermark)
                : $this->applyWatermarkToImage($tempImagePath, $watermark);

            // Extract just the watermark area from the watermarked image
            $watermarkedImg = imagecreatefromjpeg($watermarkedPath);
            $imgWidth = imagesx($watermarkedImg);
            $imgHeight = imagesy($watermarkedImg);

            // Find watermark bounds (non-white area)
            $minX = $imgWidth;
            $minY = $imgHeight;
            $maxX = 0;
            $maxY = 0;

            for ($y = 0; $y < $imgHeight; $y++) {
                for ($x = 0; $x < $imgWidth; $x++) {
                    $rgb = imagecolorat($watermarkedImg, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // If not white/light, it's part of watermark
                    if ($r < 250 || $g < 250 || $b < 250) {
                        $minX = min($minX, $x);
                        $minY = min($minY, $y);
                        $maxX = max($maxX, $x);
                        $maxY = max($maxY, $y);
                    }
                }
            }

            // Extract watermark region
            $watermarkWidth = $maxX - $minX + 1;
            $watermarkHeight = $maxY - $minY + 1;
            if ($watermarkWidth > 0 && $watermarkHeight > 0) {
                $watermarkCanvas = imagecreatetruecolor($watermarkWidth, $watermarkHeight);
                imagealphablending($watermarkCanvas, false);
                imagesavealpha($watermarkCanvas, true);
                $transparent = imagecolorallocatealpha($watermarkCanvas, 0, 0, 0, 127);
                imagefill($watermarkCanvas, 0, 0, $transparent);
                imagealphablending($watermarkCanvas, true);
                imagecopy($watermarkCanvas, $watermarkedImg, 0, 0, $minX, $minY, $watermarkWidth, $watermarkHeight);
                imagedestroy($watermarkedImg);
            } else {
                // Fallback: use entire image
                $watermarkCanvas = $watermarkedImg;
            }

            @unlink($tempImagePath);
            @unlink($watermarkedPath);
        } else {
            // Image watermark - load and resize
            if (! $watermark->imageFile || ! $watermark->imageFile->url) {
                throw new \RuntimeException('Image watermark file not found');
            }

            $watermarkUrl = $watermark->imageFile->url;
            $watermarkTempPath = $this->downloadImageToTemp($watermarkUrl);
            $watermarkImg = imagecreatefromstring(file_get_contents($watermarkTempPath));
            $wmWidth = imagesx($watermarkImg);
            $wmHeight = imagesy($watermarkImg);

            // Scale watermark
            $scale = ($watermark->scale ?? 50) / 100;
            $canvasWidth = min($videoWidth, 1920);
            $maxWidth = (int) ($canvasWidth * 0.2 * $scale);
            $scaleFactor = min(1.0, $maxWidth / $wmWidth);
            $scaledWidth = (int) ($wmWidth * $scaleFactor);
            $scaledHeight = (int) ($wmHeight * $scaleFactor);

            $watermarkCanvas = imagecreatetruecolor($scaledWidth, $scaledHeight);
            imagealphablending($watermarkCanvas, false);
            imagesavealpha($watermarkCanvas, true);
            $transparent = imagecolorallocatealpha($watermarkCanvas, 0, 0, 0, 127);
            imagefill($watermarkCanvas, 0, 0, $transparent);

            imagealphablending($watermarkCanvas, true);
            imagecopyresampled($watermarkCanvas, $watermarkImg, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $wmWidth, $wmHeight);
            imagedestroy($watermarkImg);
            @unlink($watermarkTempPath);
        }

        $outputPath = sys_get_temp_dir().'/'.uniqid('watermark_overlay_', true).'.png';
        imagepng($watermarkCanvas, $outputPath);
        imagedestroy($watermarkCanvas);

        return $outputPath;
    }

    /**
     * Check if FFmpeg is available
     */
    protected function isFFmpegAvailable(): bool
    {
        exec('ffmpeg -version 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }
}
