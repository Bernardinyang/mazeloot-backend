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
            ->with('feedback')
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
     * Get media for a specific set
     */
    public function getSetMedia(string $setUuid)
    {
        return MemoraMedia::where('media_set_uuid', $setUuid)
            ->with('feedback')
            ->orderBy('order')
            ->get();
    }

    /**
     * Create media from upload URL for a set
     */
    public function createFromUploadUrlForSet(string $setUuid, array $data): array
    {
        $set = MemoraMediaSet::query()->findOrFail($setUuid);

        // Get the maximum order for media in this set
        $maxOrder = MemoraMedia::query()->where('media_set_uuid', $setUuid)
            ->max('order') ?? -1;

        $medias = $data['media'];
        $savedMedias = [];

        foreach ($medias as $mediaData) {
            $savedMedias[] = MemoraMedia::query()->create([
                'user_uuid' => Auth::user()->uuid,
                'media_set_uuid' => $setUuid,
                'url' => $mediaData['url'],
                'order' => $maxOrder + 1,
            ]);
        }

        return $savedMedias;
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
