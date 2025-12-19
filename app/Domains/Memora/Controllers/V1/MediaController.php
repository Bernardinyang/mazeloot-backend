<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\MediaService;
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

    public function getPhaseMedia(Request $request, string $phaseType, string $phaseId): JsonResponse
    {
        $setId = $request->query('setId');
        $media = $this->mediaService->getPhaseMedia($phaseType, $phaseId, $setId);
        return ApiResponse::success(MediaResource::collection($media));
    }

    public function moveBetweenPhases(Request $request): JsonResponse
    {
        $request->validate([
            'mediaIds' => 'required|array',
            'mediaIds.*' => 'uuid',
            'fromPhase' => 'required|in:selection,proofing,collection',
            'fromPhaseId' => 'required|uuid',
            'toPhase' => 'required|in:selection,proofing,collection',
            'toPhaseId' => 'required|uuid',
        ]);

        $result = $this->mediaService->moveBetweenPhases(
            $request->input('mediaIds'),
            $request->input('fromPhase'),
            $request->input('fromPhaseId'),
            $request->input('toPhase'),
            $request->input('toPhaseId')
        );

        return ApiResponse::success([
            'movedCount' => $result['movedCount'],
            'media' => MediaResource::collection($result['media']),
        ]);
    }

    public function generateLowResCopy(string $id): JsonResponse
    {
        $media = $this->mediaService->generateLowResCopy($id);
        return ApiResponse::success([
            'id' => $media->id,
            'lowResCopyUrl' => $media->low_res_copy_url,
            'createdAt' => $media->updated_at->toIso8601String(),
        ]);
    }

    public function markSelected(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'isSelected' => 'required|boolean',
        ]);

        $media = $this->mediaService->markSelected($id, $request->input('isSelected'));
        return ApiResponse::success([
            'id' => $media->id,
            'isSelected' => $media->is_selected,
            'selectedAt' => $media->selected_at?->toIso8601String(),
        ]);
    }

    public function getRevisions(string $id): JsonResponse
    {
        $revisions = $this->mediaService->getRevisions($id);
        return ApiResponse::success($revisions);
    }

    public function markCompleted(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'isCompleted' => 'required|boolean',
        ]);

        $media = $this->mediaService->markCompleted($id, $request->input('isCompleted'));
        return ApiResponse::success([
            'id' => $media->id,
            'isCompleted' => $media->is_completed,
            'completedAt' => $media->completed_at?->toIso8601String(),
        ]);
    }

    public function addFeedback(Request $request, string $mediaId): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:text,video,audio',
            'content' => 'required|string',
            'createdBy' => 'nullable|string|max:255',
        ]);

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
}

