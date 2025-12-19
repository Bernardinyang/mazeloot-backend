<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\AddMediaFeedbackRequest;
use App\Domains\Memora\Requests\V1\UploadMediaToSetRequest;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\MediaService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function markCompleted(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'isCompleted' => ['required', 'boolean'],
        ]);

        $media = $this->mediaService->markCompleted($id, $request->input('isCompleted'));
        return ApiResponse::success([
            'id' => $media->uuid,
            'isCompleted' => $media->is_completed,
            'completedAt' => $media->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Upload media to a set
     */
    public function uploadToSet(UploadMediaToSetRequest $request, string $selectionUuid, string $setUuid): JsonResponse
    {
        $media = $this->mediaService->createFromUploadUrlForSet(
            $setUuid,
            $request->validated()
        );

        return ApiResponse::success(MediaResource::collection($media), 201);
    }

    /**
     * Add feedback to media in set
     */
    public function addFeedbackInSet(AddMediaFeedbackRequest $request, string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $feedback = $this->mediaService->addFeedback($mediaId, $request->validated());

        return ApiResponse::success([
            'id' => $feedback->uuid ?? $feedback->id,
            'mediaId' => $feedback->media_uuid ?? $feedback->media_id,
            'type' => $feedback->type,
            'content' => $feedback->content,
            'createdAt' => $feedback->created_at->toIso8601String(),
            'createdBy' => $feedback->created_by,
        ], 201);
    }

    public function addFeedback(AddMediaFeedbackRequest $request, string $mediaId): JsonResponse
    {
        $feedback = $this->mediaService->addFeedback($mediaId, $request->validated());

        return ApiResponse::success([
            'id' => $feedback->id,
            'mediaId' => $feedback->media_id,
            'type' => $feedback->type,
            'content' => $feedback->content,
            'createdAt' => $feedback->created_at->toIso8601String(),
            'createdBy' => $feedback->created_by,
        ], 201);
    }

    /**
     * Delete media from a set
     */
    public function deleteFromSet(string $selectionId, string $setUuid, string $mediaId): JsonResponse
    {
        $deleted = $this->mediaService->delete($mediaId);

        if ($deleted) {
            return ApiResponse::success([
                'message' => 'Media deleted successfully',
            ]);
        }

        return ApiResponse::error('Failed to delete media', 'DELETE_FAILED', 500);
    }

    /**
     * Get media for a specific set (guest access)
     */
    public function getSetMediaGuest(Request $request, string $id, string $setUuid): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        $media = $this->mediaService->getSetMedia($setUuid);
        return ApiResponse::success(MediaResource::collection($media));
    }

    // Guest methods

    /**
     * Get media for a specific set
     */
    public function getSetMedia(string $selectionId, string $setUuid): JsonResponse
    {
        $media = $this->mediaService->getSetMedia($setUuid);
        return ApiResponse::success(MediaResource::collection($media));
    }

}

