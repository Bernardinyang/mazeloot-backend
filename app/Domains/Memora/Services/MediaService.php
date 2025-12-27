<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaFeedback;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Services\Upload\UploadService;
use Illuminate\Support\Facades\Auth;

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
            ->with(['feedback', 'file'])
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

        if (!$media) {
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
            \Illuminate\Support\Facades\Log::error("Failed to process image for media {$mediaId}: " . $e->getMessage());
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

        if (!$media) {
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
            $lowResUrl = $media->url . '?lowres=true';

            $media->update([
                'low_res_copy_url' => $lowResUrl,
            ]);

            \Illuminate\Support\Facades\Log::info("Low-res copy generated for media: {$mediaId}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate low-res copy for media {$mediaId}: " . $e->getMessage());
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
            if (!$selectionLimitService->checkSelectionLimit($selectionId, $setId, $currentCount)) {
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

        // TODO: If revisions are stored separately, query that table
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Mark media as completed
     */
    public function markCompleted(string $id, bool $isCompleted): MemoraMedia
    {
        $media = MemoraMedia::findOrFail($id);

        $media->update([
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
        ]);

        return $media->fresh();
    }

    /**
     * Add feedback to media
     */
    public function addFeedback(string $mediaId, array $data): MemoraMediaFeedback
    {
        $media = MemoraMedia::findOrFail($mediaId);

        return MemoraMediaFeedback::create([
            'media_id' => $mediaId,
            'type' => $data['type'],
            'content' => $data['content'],
            'created_by' => $data['createdBy'] ?? null,
        ]);
    }

    /**
     * Get media for a specific set with optional sorting
     * 
     * @param string $setUuid The media set UUID
     * @param string|null $sortBy Sort field and direction (e.g., 'uploaded-desc', 'name-asc', 'date-taken-desc')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSetMedia(string $setUuid, ?string $sortBy = null, ?int $page = null, ?int $perPage = null)
    {
        $query = MemoraMedia::where('media_set_uuid', $setUuid)
            ->with(['feedback', 'file']);

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
                $relationships = ['feedback', 'file'];
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
            $relationships = ['feedback', 'file'];
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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $sortBy Format: 'field-direction' (e.g., 'uploaded-desc', 'name-asc')
     */
    protected function applyMediaSorting($query, string $sortBy): void
    {
        $parts = explode('-', $sortBy);
        $field = $parts[0] ?? 'order';
        $direction = strtoupper($parts[1] ?? 'asc');

        // Validate direction
        if (!in_array($direction, ['ASC', 'DESC'])) {
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
            $query->orderBy('memora_media.' . $dbField, $direction);
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

        // Verify user_file_uuid exists and belongs to the authenticated user
        $userFile = \App\Models\UserFile::query()
            ->where('uuid', $data['user_file_uuid'])
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        // Get the maximum order for media in this set
        $maxOrder = MemoraMedia::query()->where('media_set_uuid', $setUuid)
            ->max('order') ?? -1;

        // Create media using only user_file_uuid - all other data comes from UserFile relationship
        $media = MemoraMedia::query()->create([
            'user_uuid' => Auth::user()->uuid,
            'media_set_uuid' => $setUuid,
            'user_file_uuid' => $data['user_file_uuid'],
            'order' => $maxOrder + 1,
        ]);

        // Load the file relationship for the response
        $media->load('file');

        return $media;
    }

    /**
     * Delete media from a set
     */
    public function delete(string $mediaId): bool
    {
        $media = MemoraMedia::where('user_uuid', Auth::user()->uuid)
            ->where('uuid', $mediaId)
            ->firstOrFail();

        // Delete the media record
        // Note: We don't delete the user_file record as it may be used elsewhere
        // The user_file will remain in the database for potential recovery
        return $media->delete();
    }

    /**
     * Move media items to a different set
     * 
     * @param array $mediaUuids Array of media UUIDs to move
     * @param string $targetSetUuid Target set UUID
     * @return int Number of media items moved
     */
    public function moveMediaToSet(array $mediaUuids, string $targetSetUuid): int
    {
        // Verify target set exists and belongs to user
        $targetSet = MemoraMediaSet::query()
            ->where('uuid', $targetSetUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        // Verify all media items exist and belong to user
        $mediaItems = MemoraMedia::where('user_uuid', Auth::user()->uuid)
            ->whereIn('uuid', $mediaUuids)
            ->get();

        if ($mediaItems->count() !== count($mediaUuids)) {
            throw new \RuntimeException('One or more media items not found or access denied');
        }

        // Validate: Prevent moving to the same set
        $sourceSetUuids = $mediaItems->pluck('media_set_uuid')->unique();
        if ($sourceSetUuids->contains($targetSetUuid)) {
            throw new \RuntimeException('Cannot move media to the same set it already belongs to');
        }

        // Get the maximum order for media in the target set
        $maxOrder = MemoraMedia::where('media_set_uuid', $targetSetUuid)
            ->max('order') ?? -1;

        // Update media_set_uuid for each media item and set order
        $movedCount = 0;
        foreach ($mediaItems as $index => $media) {
            $media->update([
                'media_set_uuid' => $targetSetUuid,
                'order' => $maxOrder + 1 + $index,
            ]);
            $movedCount++;
        }

        return $movedCount;
    }

    /**
     * Copy media items to a different set
     * Creates new media entries pointing to the same user_file_uuid
     * 
     * @param array $mediaUuids Array of media UUIDs to copy
     * @param string $targetSetUuid Target set UUID
     * @return array Array of newly created media items
     */
    public function copyMediaToSet(array $mediaUuids, string $targetSetUuid): array
    {
        // Verify target set exists and belongs to user
        $targetSet = MemoraMediaSet::query()
            ->where('uuid', $targetSetUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        // Verify all media items exist and belong to user
        $mediaItems = MemoraMedia::where('user_uuid', Auth::user()->uuid)
            ->whereIn('uuid', $mediaUuids)
            ->with('file')
            ->get();

        if ($mediaItems->count() !== count($mediaUuids)) {
            throw new \RuntimeException('One or more media items not found or access denied');
        }

        // Validate: Prevent copying to the same set (would create duplicates)
        $sourceSetUuids = $mediaItems->pluck('media_set_uuid')->unique();
        if ($sourceSetUuids->contains($targetSetUuid)) {
            throw new \RuntimeException('Cannot copy media to the same set it already belongs to');
        }

        // Get the maximum order for media in the target set
        $maxOrder = MemoraMedia::where('media_set_uuid', $targetSetUuid)
            ->max('order') ?? -1;

        $copiedMedia = [];
        foreach ($mediaItems as $index => $media) {
            if (!$media->user_file_uuid) {
                // Skip if media doesn't have a user_file_uuid
                continue;
            }

            // Create new media entry with same user_file_uuid
            $newMedia = MemoraMedia::create([
                'user_uuid' => Auth::user()->uuid,
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
        if (!$media->file) {
            throw new \RuntimeException('File not found for this media');
        }

        return $media;
    }

    /**
     * Toggle star status for a media item
     *
     * @param string $mediaUuid Media UUID
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
     * @param string|null $sortBy Sort field and direction (e.g., 'uploaded-desc', 'name-asc')
     * @param int|null $page Page number for pagination
     * @param int|null $perPage Items per page
     * @return array{data: array, pagination: array}|array
     */
    public function getStarredMedia(?string $sortBy = null, ?int $page = null, ?int $perPage = null)
    {
        $user = Auth::user();

        // Get all media starred by the user
        $query = $user->starredMedia()
            ->with(['feedback', 'file', 'mediaSet.selection', 'starredByUsers' => function ($q) use ($user) {
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
            $relationships = ['feedback', 'file', 'mediaSet.selection', 'starredByUsers'];
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
     * @param string $mediaUuid Media UUID
     * @param string $newFilename New filename (extension will be preserved from original)
     * @return MemoraMedia Updated media with file relationship
     */
    public function renameMedia(string $mediaUuid, string $newFilename): MemoraMedia
    {
        // Find media and verify ownership
        $media = MemoraMedia::where('uuid', $mediaUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->with('file')
            ->firstOrFail();

        // Ensure file relationship exists
        if (!$media->file) {
            throw new \RuntimeException('File not found for this media');
        }

        // Preserve the original extension
        $originalFilename = $media->file->filename;
        $originalExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        
        // Remove extension from new filename if it exists
        $newFilenameWithoutExt = pathinfo($newFilename, PATHINFO_FILENAME);
        
        // Reconstruct filename with original extension
        $finalFilename = $originalExtension 
            ? $newFilenameWithoutExt . '.' . $originalExtension
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
     * @param string $mediaUuid Media UUID
     * @param string $newUserFileUuid New UserFile UUID
     * @return MemoraMedia Updated media with file relationship
     */
    public function replaceMedia(string $mediaUuid, string $newUserFileUuid): MemoraMedia
    {
        // Find media and verify ownership
        $media = MemoraMedia::where('uuid', $mediaUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->firstOrFail();

        // Verify the new UserFile exists and belongs to the authenticated user
        $newUserFile = \App\Models\UserFile::query()
            ->where('uuid', $newUserFileUuid)
            ->where('user_uuid', Auth::user()->uuid)
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
     * Create media from upload URL (domains never handle files directly)
     */
    public function createFromUploadUrl(array $data, string $uploadUrl): MemoraMedia
    {
        $media = MemoraMedia::create([
            'project_id' => $data['projectId'],
            'phase' => $data['phase'] ?? null,
            'phase_id' => $data['phaseId'] ?? null,
            'collection_id' => $data['collectionId'] ?? null,
            'set_id' => $data['setId'] ?? null,
            'url' => $uploadUrl,
            'thumbnail' => $data['thumbnail'] ?? null,
            'type' => $data['type'] ?? 'image',
            'filename' => $data['filename'],
            'mime_type' => $data['mimeType'] ?? 'image/jpeg',
            'size' => $data['size'] ?? 0,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'order' => $data['order'] ?? 0,
        ]);

        // Queue image processing (thumbnails, low-res copies, etc.) for images
        if (($data['type'] ?? 'image') === 'image') {
            \App\Domains\Memora\Jobs\ProcessImageJob::dispatch($media->uuid, [
                'generateThumbnail' => !$data['thumbnail'], // Only if thumbnail not already provided
                'generateLowRes' => true,
                'extractExif' => true,
            ]);
        }

        return $media;
    }
}
