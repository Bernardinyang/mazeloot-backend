<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Resources\V1\MediaSetResource;
use App\Domains\Memora\Services\MediaSetService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Proofing Media Set Controller
 *
 * Handles public/guest access to proofing media sets.
 * These endpoints are protected by guest token middleware (not user authentication).
 * Users must generate a guest token before accessing these routes.
 */
class PublicProofingMediaSetController extends Controller
{
    protected MediaSetService $mediaSetService;

    public function __construct(MediaSetService $mediaSetService)
    {
        $this->mediaSetService = $mediaSetService;
    }

    /**
     * Get all media sets for a proofing (protected by guest token)
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Allow access if proofing status is 'active' or 'completed' (view-only for completed)
        $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
        if (! in_array($proofing->status->value, ['active', 'completed'])) {
            return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
        }

        $result = $this->mediaSetService->getByProofing($id);

        return ApiResponse::success(MediaSetResource::collection($result['data']));
    }

    /**
     * Get a single media set (protected by guest token)
     */
    public function show(Request $request, string $id, string $setUuid): JsonResponse
    {
        $id = $request->route('id') ?? $id;
        $setUuid = $request->route('setId') ?? $setUuid;
        
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Allow access if proofing status is 'active' or 'completed' (view-only for completed)
        $proofing = MemoraProofing::query()->where('uuid', $id)->firstOrFail();
        if (! in_array($proofing->status->value, ['active', 'completed'])) {
            return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
        }

        $set = $this->mediaSetService->findByProofing($id, $setUuid);

        return ApiResponse::success(new MediaSetResource($set));
    }
}
