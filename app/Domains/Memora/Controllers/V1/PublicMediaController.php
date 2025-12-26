<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Resources\V1\MediaResource;
use App\Domains\Memora\Services\MediaService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public Media Controller
 * 
 * Handles public/guest access to media.
 * These endpoints are protected by guest token middleware (not user authentication).
 * Users must generate a guest token before accessing these routes.
 */
class PublicMediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Get media for a specific set (protected by guest token) with optional sorting
     */
    public function getSetMedia(Request $request, string $id, string $setUuid): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        // Allow access if selection status is 'active' or 'completed' (view-only for completed)
        $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
        if (!in_array($selection->status->value, ['active', 'completed'])) {
            return ApiResponse::error('Selection is not accessible', 'SELECTION_NOT_ACCESSIBLE', 403);
        }

        $sortBy = $request->query('sort_by');
        $media = $this->mediaService->getSetMedia($setUuid, $sortBy);
        return ApiResponse::success(MediaResource::collection($media));
    }

    /**
     * Toggle selected status for a media item (protected by guest token)
     */
    public function toggleSelected(Request $request, string $id, string $mediaId): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        // Verify selection is active
        $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
        if ($selection->status->value !== 'active') {
            return ApiResponse::error('Selection is not active', 'SELECTION_NOT_ACTIVE', 403);
        }

        try {
            // Get current selected status
            $media = \App\Domains\Memora\Models\MemoraMedia::findOrFail($mediaId);
            $isSelected = !$media->is_selected;

            // Toggle selected status
            $updatedMedia = $this->mediaService->markSelected($mediaId, $isSelected);

            return ApiResponse::success(new MediaResource($updatedMedia));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Media not found', 'MEDIA_NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'SELECTION_LIMIT_REACHED', 400);
        } catch (\Exception $e) {
            Log::error('Failed to toggle selected status for media', [
                'selection_id' => $id,
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to toggle selected status', 'TOGGLE_FAILED', 500);
        }
    }
}

