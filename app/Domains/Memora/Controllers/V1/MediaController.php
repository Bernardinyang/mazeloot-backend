<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Requests\V1\AddMediaFeedbackRequest;
use App\Domains\Memora\Requests\V1\ApplyWatermarkRequest;
use App\Domains\Memora\Requests\V1\MoveCopyMediaRequest;
use App\Domains\Memora\Requests\V1\RenameMediaRequest;
use App\Domains\Memora\Requests\V1\ReplaceMediaRequest;
use App\Domains\Memora\Requests\V1\UploadMediaToSetRequest;
use App\Domains\Memora\Resources\V1\MediaFeedbackResource;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\MediaService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getRevisions(string $id): JsonResponse
    {
        $revisions = $this->mediaService->getRevisions($id);

        return ApiResponse::success($revisions);
    }

    /**
     * Get a single media item by UUID
     */
    public function show(string $id): JsonResponse
    {
        $media = MemoraMedia::where('uuid', $id)
            ->with(['file', 'mediaSet', 'starredByUsers' => function ($query) {
                if (Auth::check()) {
                    $query->where('user_uuid', Auth::user()->uuid);
                }
            }])
            ->firstOrFail();

        return ApiResponse::success(new MediaResource($media));
    }

    public function markCompleted(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'isCompleted' => ['required', 'boolean'],
        ]);

        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        $media = $this->mediaService->markCompleted($id, $request->input('isCompleted'), $userId);

        return ApiResponse::success(new MediaResource($media));
    }

    /**
     * Upload media to a set
     * Uses user_file_uuid from the memora_media migration to store media
     */
    public function uploadToSet(UploadMediaToSetRequest $request, string $selectionUuid, string $setUuid): JsonResponse
    {
        $media = $this->mediaService->createFromUploadUrlForSet(
            $setUuid,
            $request->validated()
        );

        return ApiResponse::success(new MediaResource($media), 201);
    }

    /**
     * Add feedback to media in set
     */
    public function addFeedbackInSet(AddMediaFeedbackRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $feedback = $this->mediaService->addFeedback($mediaId, $request->validated());

        return ApiResponse::success(new MediaFeedbackResource($feedback), 201);
    }

    /**
     * Add feedback to media
     * Note: Route parameters are resolved by name, so we need to accept all route parameters
     * to ensure Laravel resolves them correctly.
     * Route: /proofing/{proofingId}/sets/{setId}/media/{mediaId}/feedback
     */
    public function addFeedback(AddMediaFeedbackRequest $request, string $proofingId, string $setId, string $mediaId): JsonResponse
    {
        try {
            // Log to help debug parameter resolution
            Log::info('Adding feedback to media', [
                'media_id' => $mediaId,
                'proofing_id' => $proofingId,
                'set_id' => $setId,
                'media_id_length' => strlen($mediaId),
                'is_uuid_format' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $mediaId),
            ]);

            // Validate that mediaId is not the same as proofingId (safety check)
            if ($mediaId === $proofingId) {
                Log::error('CRITICAL: mediaId matches proofingId! Route parameter resolution issue.', [
                    'media_id' => $mediaId,
                    'proofing_id' => $proofingId,
                    'set_id' => $setId,
                ]);

                return ApiResponse::error('Invalid media ID', 'INVALID_MEDIA_ID', 400);
            }

            $feedback = $this->mediaService->addFeedback($mediaId, $request->validated());

            return ApiResponse::success(new MediaFeedbackResource($feedback), 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Media not found when adding feedback', [
                'media_id' => $mediaId,
                'proofing_id' => $proofingId,
                'set_id' => $setId,
                'exception_message' => $e->getMessage(),
            ]);

            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to add feedback', [
                'media_id' => $mediaId,
                'proofing_id' => $proofingId,
                'set_id' => $setId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to add feedback', 'FEEDBACK_FAILED', 500);
        }
    }

    /**
     * Update feedback
     * Note: Route parameters include proofingId and setId for validation
     */
    public function updateFeedback(Request $request, string $proofingId, string $setId, string $mediaId, string $feedbackId): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string'],
        ]);

        try {
            // Validate that media exists and belongs to the set/proofing
            $media = MemoraMedia::where('uuid', $mediaId)->first();
            if (! $media) {
                return ApiResponse::error('Media not found or does not belong to the specified set', 'MEDIA_NOT_FOUND', 404);
            }

            if ($media->media_set_uuid !== $setId) {
                return ApiResponse::error('Media not found or does not belong to the specified set', 'MEDIA_NOT_FOUND', 404);
            }

            $set = MemoraMediaSet::where('uuid', $setId)->first();
            if (! $set) {
                return ApiResponse::error('Set not found or does not belong to the specified proofing', 'SET_NOT_FOUND', 404);
            }

            // Validate set belongs to proofing
            // If phase/phase_id are null, we still allow if media belongs to the set
            $setBelongsToProofing = ($set->phase === 'proofing' && $set->phase_id === $proofingId);
            $mediaBelongsToSet = ($media->media_set_uuid === $setId);

            if (! $setBelongsToProofing && ! $mediaBelongsToSet) {
                return ApiResponse::error('Set not found or does not belong to the specified proofing', 'SET_NOT_FOUND', 404);
            }

            $feedback = $this->mediaService->updateFeedback($feedbackId, $request->only('content'));

            return ApiResponse::success(new MediaFeedbackResource($feedback));
        } catch (\Exception $e) {
            Log::error('Failed to update feedback', [
                'feedback_id' => $feedbackId,
                'media_id' => $mediaId,
                'proofing_id' => $proofingId,
                'set_id' => $setId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error($e->getMessage(), 'UPDATE_FAILED', 400);
        }
    }

    /**
     * Delete feedback
     * Note: Route parameters include proofingId and setId for validation
     */
    public function deleteFeedback(string $proofingId, string $setId, string $mediaId, string $feedbackId): JsonResponse
    {
        try {
            // Validate that media exists and belongs to the set/proofing
            $media = MemoraMedia::where('uuid', $mediaId)->first();
            if (! $media) {
                Log::warning('Media not found for deleteFeedback', [
                    'media_id' => $mediaId,
                    'set_id' => $setId,
                    'proofing_id' => $proofingId,
                ]);

                return ApiResponse::error('Media not found or does not belong to the specified set', 'MEDIA_NOT_FOUND', 404);
            }

            if ($media->media_set_uuid !== $setId) {
                Log::warning('Media does not belong to set', [
                    'media_id' => $mediaId,
                    'media_set_uuid' => $media->media_set_uuid,
                    'expected_set_id' => $setId,
                    'proofing_id' => $proofingId,
                ]);

                return ApiResponse::error('Media not found or does not belong to the specified set', 'MEDIA_NOT_FOUND', 404);
            }

            $set = MemoraMediaSet::where('uuid', $setId)->first();
            if (! $set) {
                Log::warning('Set not found for deleteFeedback', [
                    'set_id' => $setId,
                    'proofing_id' => $proofingId,
                    'media_id' => $mediaId,
                    'all_sets_for_proofing' => MemoraMediaSet::where('phase', 'proofing')
                        ->where('phase_id', $proofingId)
                        ->pluck('uuid')
                        ->toArray(),
                ]);

                return ApiResponse::error('Set not found or does not belong to the specified proofing', 'SET_NOT_FOUND', 404);
            }

            Log::info('Set found for deleteFeedback', [
                'set_id' => $setId,
                'set_uuid' => $set->uuid,
                'set_phase' => $set->phase,
                'set_phase_id' => $set->phase_id,
                'expected_proofing_id' => $proofingId,
                'media_id' => $mediaId,
                'media_set_uuid' => $media->media_set_uuid,
                'phase_match' => $set->phase === 'proofing',
                'phase_id_match' => $set->phase_id === $proofingId,
            ]);

            // Validate set belongs to proofing
            // If phase/phase_id are null, we still allow if media belongs to the set
            // (This handles cases where sets might not have phase/phase_id set)
            $setBelongsToProofing = ($set->phase === 'proofing' && $set->phase_id === $proofingId);
            $mediaBelongsToSet = ($media->media_set_uuid === $setId);

            if (! $setBelongsToProofing && ! $mediaBelongsToSet) {
                Log::warning('Set does not belong to proofing and media does not belong to set', [
                    'set_id' => $setId,
                    'set_uuid' => $set->uuid,
                    'set_phase' => $set->phase,
                    'set_phase_id' => $set->phase_id,
                    'expected_proofing_id' => $proofingId,
                    'media_id' => $mediaId,
                    'media_set_uuid' => $media->media_set_uuid,
                    'phase_match' => $set->phase === 'proofing',
                    'phase_id_match' => $set->phase_id === $proofingId,
                    'media_belongs_to_set' => $mediaBelongsToSet,
                ]);

                return ApiResponse::error('Set not found or does not belong to the specified proofing', 'SET_NOT_FOUND', 404);
            }

            // If set phase/phase_id are null but media belongs to set, log a warning but allow
            if (! $setBelongsToProofing && $mediaBelongsToSet) {
                Log::warning('Set phase/phase_id are null but media belongs to set - allowing operation', [
                    'set_id' => $setId,
                    'set_uuid' => $set->uuid,
                    'set_phase' => $set->phase,
                    'set_phase_id' => $set->phase_id,
                    'expected_proofing_id' => $proofingId,
                    'media_id' => $mediaId,
                    'media_set_uuid' => $media->media_set_uuid,
                ]);
            }

            $deleted = $this->mediaService->deleteFeedback($feedbackId);
            if ($deleted) {
                return ApiResponse::success(['message' => 'Feedback deleted successfully']);
            }

            return ApiResponse::error('Failed to delete feedback', 'DELETE_FAILED', 500);
        } catch (\Exception $e) {
            Log::error('Failed to delete feedback', [
                'feedback_id' => $feedbackId,
                'media_id' => $mediaId,
                'proofing_id' => $proofingId,
                'set_id' => $setId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error($e->getMessage(), 'DELETE_FAILED', 400);
        }
    }

    /**
     * Delete media from a set
     */
    public function deleteFromSet(string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        // Verify media belongs to set
        $media = MemoraMedia::findOrFail($mediaId);
        if ($media->media_set_uuid !== $setUuid) {
            return ApiResponse::error('Media does not belong to this set', 'MEDIA_NOT_IN_SET', 403);
        }

        $deleted = $this->mediaService->delete($mediaId, $userId);

        if ($deleted) {
            return ApiResponse::success([
                'message' => 'Media deleted successfully',
            ]);
        }

        return ApiResponse::error('Failed to delete media', 'DELETE_FAILED', 500);
    }

    /**
     * Delete media directly (without selection/set context)
     */
    public function deleteDirect(string $mediaId): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        try {
            $deleted = $this->mediaService->delete($mediaId, $userId);

            if ($deleted) {
                return ApiResponse::success([
                    'message' => 'Media deleted successfully',
                ]);
            }

            return ApiResponse::error('Failed to delete media', 'DELETE_FAILED', 500);
        } catch (\Exception $e) {
            Log::error('Failed to delete media', [
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete media: '.$e->getMessage(), 'DELETE_FAILED', 500);
        }
    }

    /**
     * Rename media by updating the UserFile's filename
     */
    public function rename(RenameMediaRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        // Verify media belongs to set
        $media = MemoraMedia::findOrFail($mediaId);
        if ($media->media_set_uuid !== $setUuid) {
            return ApiResponse::error('Media does not belong to this set', 'MEDIA_NOT_IN_SET', 403);
        }

        try {
            $validated = $request->validated();
            $media = $this->mediaService->renameMedia($mediaId, $validated['filename'], $userId);

            return ApiResponse::success(new MediaResource($media));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'RENAME_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to rename media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to rename media', 'RENAME_FAILED', 500);
        }
    }

    /**
     * Replace media file by updating the user_file_uuid
     */
    public function replace(ReplaceMediaRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        // Verify media belongs to set
        $media = MemoraMedia::findOrFail($mediaId);
        if ($media->media_set_uuid !== $setUuid) {
            return ApiResponse::error('Media does not belong to this set', 'MEDIA_NOT_IN_SET', 403);
        }

        try {
            $validated = $request->validated();
            $media = $this->mediaService->replaceMedia($mediaId, $validated['user_file_uuid'], $userId);

            return ApiResponse::success(new MediaResource($media));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media or file not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'REPLACE_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to replace media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to replace media', 'REPLACE_FAILED', 500);
        }
    }

    /**
     * Apply watermark to media
     */
    public function applyWatermark(ApplyWatermarkRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        try {
            $validated = $request->validated();
            $previewStyle = $validated['previewStyle'] ?? false;
            $media = $this->mediaService->applyWatermark($mediaId, $validated['watermarkUuid'], $userId, $previewStyle);

            return ApiResponse::success(new MediaResource($media));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media or watermark not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'WATERMARK_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to apply watermark', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to apply watermark', 'WATERMARK_FAILED', 500);
        }
    }

    /**
     * Remove watermark from media
     */
    public function removeWatermark(Request $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        try {
            $media = $this->mediaService->removeWatermark($mediaId, $userId);

            return ApiResponse::success(new MediaResource($media));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'WATERMARK_REMOVE_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to remove watermark', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to remove watermark', 'WATERMARK_REMOVE_FAILED', 500);
        }
    }

    /**
     * Move media items to a different set
     */
    public function moveToSet(MoveCopyMediaRequest $request, string $selectionId, string $setUuid): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        try {
            $validated = $request->validated();
            $movedCount = $this->mediaService->moveMediaToSet(
                $validated['media_uuids'],
                $validated['target_set_uuid'],
                $userId
            );

            return ApiResponse::success([
                'message' => 'Media moved successfully',
                'moved_count' => $movedCount,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Target set not found', 'SET_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'MOVE_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to move media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to move media', 'MOVE_FAILED', 500);
        }
    }

    /**
     * Copy media items to a different set
     */
    public function copyToSet(MoveCopyMediaRequest $request, string $selectionId, string $setUuid): JsonResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        try {
            $validated = $request->validated();
            $copiedMedia = $this->mediaService->copyMediaToSet(
                $validated['media_uuids'],
                $validated['target_set_uuid'],
                $userId
            );

            return ApiResponse::success([
                'message' => 'Media copied successfully',
                'copied_count' => count($copiedMedia),
                'media' => MediaResource::collection($copiedMedia),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Target set not found', 'SET_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'COPY_FAILED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to copy media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to copy media', 'COPY_FAILED', 500);
        }
    }

    // Authenticated user methods

    /**
     * Get media for a specific set with optional sorting
     */
    /**
     * Get media for a set
     * Unified route for selections and proofing (both standalone and project-based)
     * Routes: /selections/{selectionId}/sets/{setId}/media or /proofing/{proofingId}/sets/{setId}/media
     * For project-based: pass ?projectId=xxx as query parameter
     */
    public function getSetMedia(Request $request, string $parentId, string $setId): JsonResponse
    {
        $setUuid = $setId;

        $sortBy = $request->query('sort_by');
        $page = $request->has('page') ? max(1, (int) $request->query('page', 1)) : null;
        $perPage = $request->has('per_page') ? max(1, min(100, (int) $request->query('per_page', 10))) : null;

        $result = $this->mediaService->getSetMedia($setUuid, $sortBy, $page, $perPage);

        // If paginated, result is already formatted with data and pagination
        // If not paginated, wrap in MediaResource collection
        if (is_array($result) && isset($result['data']) && isset($result['pagination'])) {
            return ApiResponse::success($result);
        }

        return ApiResponse::success(MediaResource::collection($result));
    }

    /**
     * Toggle star status for a media item (with selection/set context)
     */
    public function toggleStar(string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        try {
            $result = $this->mediaService->toggleStar($mediaId);

            return ApiResponse::success($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle star for media', [
                'selection_id' => $selectionId,
                'set_uuid' => $setUuid,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle star', 'STAR_FAILED', 500);
        }
    }

    /**
     * Toggle star status for a media item (direct, without selection/set context)
     */
    public function toggleStarDirect(string $mediaId): JsonResponse
    {
        try {
            $result = $this->mediaService->toggleStar($mediaId);

            return ApiResponse::success($result);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle star for media', [
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle star', 'STAR_FAILED', 500);
        }
    }

    /**
     * Get all starred media for the authenticated user
     */
    public function getStarredMedia(Request $request): JsonResponse
    {
        $sortBy = $request->query('sort_by');
        $page = $request->has('page') ? max(1, (int) $request->query('page', 1)) : null;
        $perPage = $request->has('per_page') ? max(1, min(100, (int) $request->query('per_page', 10))) : null;

        $result = $this->mediaService->getStarredMedia($sortBy, $page, $perPage);

        // If paginated, result is already formatted with data and pagination
        // If not paginated, wrap in MediaResource collection
        if (is_array($result) && isset($result['data']) && isset($result['pagination'])) {
            return ApiResponse::success($result);
        }

        return ApiResponse::success($result);
    }

    /**
     * Get all media for the authenticated user
     */
    public function getUserMedia(Request $request): JsonResponse
    {
        $sortBy = $request->query('sort_by');
        $page = $request->has('page') ? max(1, (int) $request->query('page', 1)) : null;
        $perPage = $request->has('per_page') ? max(1, min(100, (int) $request->query('per_page', 10))) : null;

        $result = $this->mediaService->getUserMedia($sortBy, $page, $perPage);

        // If paginated, result is already formatted with data and pagination
        // If not paginated, wrap in MediaResource collection
        if (is_array($result) && isset($result['data']) && isset($result['pagination'])) {
            return ApiResponse::success($result);
        }

        return ApiResponse::success($result);
    }

    /**
     * Get all featured media for the authenticated user
     */
    public function getFeaturedMedia(Request $request): JsonResponse
    {
        $sortBy = $request->query('sort_by');
        $page = $request->has('page') ? max(1, (int) $request->query('page', 1)) : null;
        $perPage = $request->has('per_page') ? max(1, min(100, (int) $request->query('per_page', 10))) : null;

        $result = $this->mediaService->getFeaturedMedia($sortBy, $page, $perPage);

        // If paginated, result is already formatted with data and pagination
        // If not paginated, wrap in MediaResource collection
        if (is_array($result) && isset($result['data']) && isset($result['pagination'])) {
            return ApiResponse::success($result);
        }

        return ApiResponse::success($result);
    }

    /**
     * Download original image file by media UUID
     */
    public function download(string $mediaUuid): StreamedResponse|Response|JsonResponse|RedirectResponse
    {
        try {
            Log::info('Download request started', ['media_uuid' => $mediaUuid]);

            // First check if media belongs to raw file and if download PIN is needed
            // We need to load mediaSet separately to avoid ownership check blocking non-owners with PIN
            $media = \App\Domains\Memora\Models\MemoraMedia::where('uuid', $mediaUuid)
                ->with(['file', 'mediaSet'])
                ->firstOrFail();
            
            $mediaSet = $media->mediaSet;
            $requiresPinCheck = false;
            
            if ($mediaSet && $mediaSet->raw_file_uuid) {
                $rawFile = \App\Domains\Memora\Models\MemoraRawFile::where('uuid', $mediaSet->raw_file_uuid)->first();
                if ($rawFile) {
                    $isOwner = auth()->check() && auth()->user()->uuid === $rawFile->user_uuid;
                    
                    // Check download PIN for non-owners
                    if (!$isOwner) {
                        $settings = $rawFile->settings ?? [];
                        $downloadSettings = $settings['download'] ?? [];
                        $downloadPinEnabled = $downloadSettings['downloadPinEnabled'] ?? !empty($settings['downloadPin'] ?? null);
                        $downloadPin = $downloadSettings['downloadPin'] ?? $settings['downloadPin'] ?? null;
                        
                        if ($downloadPinEnabled && $downloadPin) {
                            $requiresPinCheck = true;
                            $providedPin = request()->header('X-Download-PIN');
                            if (!$providedPin || $providedPin !== $downloadPin) {
                                return ApiResponse::error('Download PIN required', 'DOWNLOAD_PIN_REQUIRED', 401);
                            }
                        }
                    }
                }
            }
            
            // For owners or if PIN check passed, verify ownership via service (unless PIN was used for raw file)
            if (!$requiresPinCheck) {
                $media = $this->mediaService->getMediaForDownload($mediaUuid);
            } else {
                // PIN check passed for non-owner, just verify file exists
                if (!$media->file) {
                    throw new \RuntimeException('File not found for this media');
                }
            }
            
            $file = $media->file;

            if (! $file) {
                Log::error('File relationship not loaded for media', [
                    'media_uuid' => $mediaUuid,
                    'media_id' => $media->id ?? null,
                    'user_file_uuid' => $media->user_file_uuid ?? null,
                ]);

                return ApiResponse::error('File not found for this media', 'FILE_NOT_FOUND', 404);
            }

            // Get the file path and URL from the UserFile model
            $filePath = $file->path;
            $fileUrl = $file->url;

            Log::info('File information', [
                'media_uuid' => $mediaUuid,
                'file_path' => $filePath,
                'file_url' => $fileUrl,
                'file_uuid' => $file->uuid ?? null,
                'filename' => $file->filename ?? null,
            ]);

            // Priority 1: If we have a cloud storage URL, download and stream it to avoid CORS issues
            if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                // Check if it's a cloud storage URL
                $isCloudStorage = str_contains($fileUrl, 'amazonaws.com') ||
                    str_contains($fileUrl, 'r2.cloudflarestorage.com') ||
                    str_contains($fileUrl, 'r2.dev') ||
                    str_contains($fileUrl, 'cloudflare') ||
                    str_contains($fileUrl, 's3.') ||
                    str_contains($fileUrl, '.s3.');

                if ($isCloudStorage) {
                    // For cloud storage, download the file and stream it to avoid CORS issues
                    Log::info('Downloading from cloud storage URL', [
                        'media_uuid' => $mediaUuid,
                        'url' => $fileUrl,
                    ]);

                    try {
                        // Download the file from the cloud storage URL
                        $fileContents = file_get_contents($fileUrl);

                        if ($fileContents === false) {
                            throw new \RuntimeException('Failed to download file from cloud storage');
                        }

                        // Get the filename for download
                        $filename = $file->filename ?? 'download';

                        // Ensure filename has proper extension
                        if (! pathinfo($filename, PATHINFO_EXTENSION)) {
                            // Try to get extension from mime type
                            $extension = match ($file->mime_type) {
                                'image/jpeg', 'image/jpg' => 'jpg',
                                'image/png' => 'png',
                                'image/gif' => 'gif',
                                'image/webp' => 'webp',
                                'video/mp4' => 'mp4',
                                'video/mpeg' => 'mpeg',
                                default => 'bin',
                            };
                            $filename .= '.'.$extension;
                        }

                        // Stream the file content as download
                        return response($fileContents)
                            ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
                            ->header('Content-Length', strlen($fileContents));
                    } catch (\Exception $e) {
                        Log::error('Failed to download from cloud storage', [
                            'media_uuid' => $mediaUuid,
                            'url' => $fileUrl,
                            'error' => $e->getMessage(),
                        ]);
                        // Fall through to try other methods
                    }
                }
            }

            // Priority 2: If we have a path, try to download from storage
            if ($filePath) {
                // If it's a local storage URL, extract the path
                if ($fileUrl && str_contains($fileUrl, '/storage/')) {
                    $parsedPath = parse_url($fileUrl, PHP_URL_PATH);
                    if ($parsedPath) {
                        $filePath = str_replace('/storage/', '', $parsedPath);
                    }
                }

                // Determine which disk to use
                $disksToCheck = ['public', 'local'];

                // Only check cloud disks if they're configured
                try {
                    if (config('filesystems.disks.s3.key')) {
                        $disksToCheck[] = 's3';
                    }
                } catch (\Exception $e) {
                    // Ignore config errors
                }

                try {
                    if (config('filesystems.disks.r2.key')) {
                        $disksToCheck[] = 'r2';
                    }
                } catch (\Exception $e) {
                    // Ignore config errors
                }

                $foundDisk = null;
                foreach ($disksToCheck as $checkDisk) {
                    try {
                        if (Storage::disk($checkDisk)->exists($filePath)) {
                            $foundDisk = $checkDisk;
                            Log::info('File found on disk', [
                                'media_uuid' => $mediaUuid,
                                'disk' => $checkDisk,
                                'file_path' => $filePath,
                            ]);
                            break;
                        }
                    } catch (\Exception $e) {
                        // Skip this disk if there's an error (e.g., not configured)
                        Log::debug('Error checking disk', [
                            'disk' => $checkDisk,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }
                }

                if ($foundDisk) {
                    // Get the filename for download
                    $filename = $file->filename ?? 'download';

                    // Ensure filename has proper extension
                    if (! pathinfo($filename, PATHINFO_EXTENSION)) {
                        // Try to get extension from mime type
                        $extension = match ($file->mime_type) {
                            'image/jpeg', 'image/jpg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            'video/mp4' => 'mp4',
                            'video/mpeg' => 'mpeg',
                            default => 'bin',
                        };
                        $filename .= '.'.$extension;
                    }

                    Log::info('Downloading file from storage', [
                        'media_uuid' => $mediaUuid,
                        'disk' => $foundDisk,
                        'file_path' => $filePath,
                        'filename' => $filename,
                    ]);

                    // Stream the file for download
                    try {
                        return Storage::disk($foundDisk)->download($filePath, $filename, [
                            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to download file from storage', [
                            'media_uuid' => $mediaUuid,
                            'disk' => $foundDisk,
                            'file_path' => $filePath,
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        // Fallback to URL if available
                        if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                            try {
                                return redirect($fileUrl);
                            } catch (\Exception $redirectError) {
                                Log::error('Failed to redirect to URL', [
                                    'media_uuid' => $mediaUuid,
                                    'url' => $fileUrl,
                                    'error' => $redirectError->getMessage(),
                                ]);

                                return response()->json([
                                    'data' => ['download_url' => $fileUrl],
                                    'status' => 200,
                                    'statusText' => 'OK',
                                ], 200);
                            }
                        }

                        throw $e; // Re-throw to be caught by outer catch
                    }
                }
            }

            // Priority 3: Fallback - if we have a URL but no path, try to download from URL
            if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                Log::info('File not found on storage, attempting to download from URL', [
                    'media_uuid' => $mediaUuid,
                    'url' => $fileUrl,
                ]);

                try {
                    // Download the file from the URL
                    $fileContents = file_get_contents($fileUrl);

                    if ($fileContents === false) {
                        throw new \RuntimeException('Failed to download file from URL');
                    }

                    // Get the filename for download
                    $filename = $file->filename ?? 'download';

                    // Ensure filename has proper extension
                    if (! pathinfo($filename, PATHINFO_EXTENSION)) {
                        // Try to get extension from mime type
                        $extension = match ($file->mime_type) {
                            'image/jpeg', 'image/jpg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            'video/mp4' => 'mp4',
                            'video/mpeg' => 'mpeg',
                            default => 'bin',
                        };
                        $filename .= '.'.$extension;
                    }

                    // Stream the file content as download
                    return response($fileContents)
                        ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                        ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
                        ->header('Content-Length', strlen($fileContents));
                } catch (\Exception $e) {
                    Log::error('Failed to download from URL', [
                        'media_uuid' => $mediaUuid,
                        'url' => $fileUrl,
                        'error' => $e->getMessage(),
                    ]);

                    return ApiResponse::error('Failed to download file from URL: '.$e->getMessage(), 'DOWNLOAD_ERROR', 500);
                }
            }

            // If we get here, we can't download the file
            Log::error('Unable to download file - no path or URL available', [
                'media_uuid' => $mediaUuid,
                'file_path' => $filePath,
                'file_url' => $fileUrl,
            ]);

            return ApiResponse::error('File not available for download', 'FILE_NOT_FOUND', 404);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Media not found', [
                'media_uuid' => $mediaUuid,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            Log::error('Runtime error downloading media', [
                'media_uuid' => $mediaUuid,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error($e->getMessage(), 'FILE_ERROR', 500);
        } catch (\Exception $e) {
            Log::error('Failed to download media', [
                'media_uuid' => $mediaUuid,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::error('Failed to download file: '.$e->getMessage(), 'DOWNLOAD_ERROR', 500);
        }
    }

    /**
     * Serve image file by media UUID with CORS headers
     */
    public function serve(string $mediaUuid): StreamedResponse|Response|JsonResponse
    {
        try {
            $media = $this->mediaService->getMediaForDownload($mediaUuid);
            $file = $media->file;

            if (! $file) {
                return ApiResponse::error('File not found for this media', 'FILE_NOT_FOUND', 404);
            }

            $filePath = $file->path;
            $fileUrl = $file->url;

            // Priority 1: If we have a cloud storage URL, proxy it with CORS headers
            if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                $isCloudStorage = str_contains($fileUrl, 'amazonaws.com') ||
                    str_contains($fileUrl, 'r2.cloudflarestorage.com') ||
                    str_contains($fileUrl, 'r2.dev') ||
                    str_contains($fileUrl, 'cloudflare') ||
                    str_contains($fileUrl, 's3.') ||
                    str_contains($fileUrl, '.s3.');

                if ($isCloudStorage) {
                    try {
                        $fileContents = file_get_contents($fileUrl);
                        if ($fileContents === false) {
                            throw new \RuntimeException('Failed to fetch file from cloud storage');
                        }

                        return response($fileContents)
                            ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                            ->header('Content-Length', strlen($fileContents))
                            ->header('Access-Control-Allow-Origin', '*')
                            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                            ->header('Access-Control-Allow-Headers', 'Content-Type')
                            ->header('Cache-Control', 'public, max-age=31536000');
                    } catch (\Exception $e) {
                        Log::error('Failed to serve from cloud storage', [
                            'media_uuid' => $mediaUuid,
                            'url' => $fileUrl,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Priority 2: Serve from storage disk
            if ($filePath) {
                if ($fileUrl && str_contains($fileUrl, '/storage/')) {
                    $parsedPath = parse_url($fileUrl, PHP_URL_PATH);
                    if ($parsedPath) {
                        $filePath = str_replace('/storage/', '', $parsedPath);
                    }
                }

                $disksToCheck = ['public', 'local'];
                if (config('filesystems.disks.s3.key')) {
                    $disksToCheck[] = 's3';
                }
                if (config('filesystems.disks.r2.key')) {
                    $disksToCheck[] = 'r2';
                }

                foreach ($disksToCheck as $checkDisk) {
                    try {
                        if (Storage::disk($checkDisk)->exists($filePath)) {
                            // For local disks, use path() method
                            if (in_array($checkDisk, ['public', 'local'])) {
                                return response()->file(Storage::disk($checkDisk)->path($filePath))
                                    ->header('Access-Control-Allow-Origin', '*')
                                    ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                                    ->header('Access-Control-Allow-Headers', 'Content-Type')
                                    ->header('Cache-Control', 'public, max-age=31536000');
                            }

                            // For cloud storage, download and serve with CORS
                            $fileContents = Storage::disk($checkDisk)->get($filePath);

                            return response($fileContents)
                                ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                                ->header('Content-Length', strlen($fileContents))
                                ->header('Access-Control-Allow-Origin', '*')
                                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                                ->header('Access-Control-Allow-Headers', 'Content-Type')
                                ->header('Cache-Control', 'public, max-age=31536000');
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            // Priority 3: Fallback to URL redirect with CORS headers
            if ($fileUrl && (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://'))) {
                try {
                    $fileContents = file_get_contents($fileUrl);
                    if ($fileContents !== false) {
                        return response($fileContents)
                            ->header('Content-Type', $file->mime_type ?? 'application/octet-stream')
                            ->header('Content-Length', strlen($fileContents))
                            ->header('Access-Control-Allow-Origin', '*')
                            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                            ->header('Access-Control-Allow-Headers', 'Content-Type')
                            ->header('Cache-Control', 'public, max-age=31536000');
                    }
                } catch (\Exception $e) {
                    // Fall through
                }
            }

            return ApiResponse::error('File not available', 'FILE_NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to serve media', [
                'media_uuid' => $mediaUuid,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to serve file: '.$e->getMessage(), 'SERVE_ERROR', 500);
        }
    }

    /**
     * Toggle featured status for media
     */
    public function toggleFeatured(string $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (! $userId) {
                return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
            }

            $media = MemoraMedia::where('uuid', $id)
                ->where('user_uuid', Auth::user()->uuid)
                ->firstOrFail();

            $isFeatured = ! $media->is_featured;

            $media->update([
                'is_featured' => $isFeatured,
                'featured_at' => $isFeatured ? now() : null,
            ]);

            return ApiResponse::success(new MediaResource($media->fresh()));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle featured status', [
                'media_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle featured status', 'TOGGLE_FAILED', 500);
        }
    }

    /**
     * Toggle recommended status for media in a selection set
     * Only allowed for selections, not raw files, proofing, or collections
     */
    public function toggleRecommended(string $selectionId, string $setId, string $mediaId): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (! $userId) {
                return ApiResponse::error('Unauthorized', 'UNAUTHORIZED', 401);
            }

            $media = MemoraMedia::where('uuid', $mediaId)
                ->where('user_uuid', Auth::user()->uuid)
                ->where('media_set_uuid', $setId)
                ->with('mediaSet')
                ->firstOrFail();

            // Verify media belongs to a selection phase (not raw file, proofing, or collection)
            $mediaSet = $media->mediaSet;
            if (! $mediaSet || ! $mediaSet->selection_uuid || $mediaSet->raw_file_uuid || $mediaSet->proof_uuid || $mediaSet->collection_uuid) {
                return ApiResponse::error('Recommended status is only available for selection phase', 'INVALID_PHASE', 403);
            }

            // Verify selection ID matches
            if ($mediaSet->selection_uuid !== $selectionId) {
                return ApiResponse::error('Media does not belong to this selection', 'INVALID_SELECTION', 403);
            }

            $isRecommended = ! $media->is_recommended;

            $media->update([
                'is_recommended' => $isRecommended,
                'recommended_at' => $isRecommended ? now() : null,
            ]);

            return ApiResponse::success(new MediaResource($media->fresh()));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            Log::error('Failed to toggle recommended status', [
                'selection_id' => $selectionId,
                'set_id' => $setId,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to toggle recommended status', 'TOGGLE_FAILED', 500);
        }
    }
}
