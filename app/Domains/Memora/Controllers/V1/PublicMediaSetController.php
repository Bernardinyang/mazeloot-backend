<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraRawFile;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Resources\V1\MediaSetResource;
use App\Domains\Memora\Services\MediaSetService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Media Set Controller
 *
 * Handles public/guest access to media sets.
 * These endpoints are protected by guest token middleware (not user authentication).
 * Users must generate a guest token before accessing these routes.
 */
class PublicMediaSetController extends Controller
{
    protected MediaSetService $mediaSetService;

    public function __construct(MediaSetService $mediaSetService)
    {
        $this->mediaSetService = $mediaSetService;
    }

    /**
     * Get all media sets for a selection/raw file (protected by guest token)
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Check token type and verify it belongs to the correct phase
        $isValid = false;
        $phase = null;

        // Check for raw file token
        if (isset($guestToken->raw_file_uuid)) {
            if ($guestToken->raw_file_uuid !== $id) {
                return ApiResponse::error('Token does not match raw file', 'INVALID_TOKEN', 403);
            }
            $phase = 'rawFile';
            $isValid = true;
        }
        // Check for selection token (default)
        elseif (isset($guestToken->selection_uuid)) {
            if ($guestToken->selection_uuid !== $id) {
                return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
            }
            $phase = 'selection';
            $isValid = true;
        }

        if (! $isValid) {
            return ApiResponse::error('Invalid guest token', 'INVALID_TOKEN', 403);
        }

        // Verify phase is accessible based on type
        if ($phase === 'rawFile') {
            $rawFile = MemoraRawFile::query()->where('uuid', $id)->firstOrFail();
            if (! in_array($rawFile->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }
            $result = $this->mediaSetService->getByRawFile($id);
            return ApiResponse::success(MediaSetResource::collection($result['data'] ?? $result));
        } else {
            $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
            if (! in_array($selection->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Selection is not accessible', 'SELECTION_NOT_ACCESSIBLE', 403);
            }
            $sets = $this->mediaSetService->getBySelection($id);
            return ApiResponse::success(MediaSetResource::collection($sets));
        }
    }

    /**
     * Get a single media set (protected by guest token)
     */
    public function show(Request $request, string $id, string $setUuid): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Check token type and verify it belongs to the correct phase
        $isValid = false;
        $phase = null;

        // Check for raw file token
        if (isset($guestToken->raw_file_uuid)) {
            if ($guestToken->raw_file_uuid !== $id) {
                return ApiResponse::error('Token does not match raw file', 'INVALID_TOKEN', 403);
            }
            $phase = 'rawFile';
            $isValid = true;
        }
        // Check for selection token (default)
        elseif (isset($guestToken->selection_uuid)) {
            if ($guestToken->selection_uuid !== $id) {
                return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
            }
            $phase = 'selection';
            $isValid = true;
        }

        if (! $isValid) {
            return ApiResponse::error('Invalid guest token', 'INVALID_TOKEN', 403);
        }

        // Verify phase is accessible based on type
        if ($phase === 'rawFile') {
            $rawFile = MemoraRawFile::query()->where('uuid', $id)->firstOrFail();
            if (! in_array($rawFile->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Raw file is not accessible', 'RAW_FILE_NOT_ACCESSIBLE', 403);
            }
            $set = $this->mediaSetService->findByRawFile($id, $setUuid);
        } else {
            $selection = MemoraSelection::query()->where('uuid', $id)->firstOrFail();
            if (! in_array($selection->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Selection is not accessible', 'SELECTION_NOT_ACCESSIBLE', 403);
            }
            $set = $this->mediaSetService->find($id, $setUuid);
        }

        return ApiResponse::success(new MediaSetResource($set));
    }
}
