<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Requests\V1\CompleteSelectionRequest;
use App\Domains\Memora\Resources\V1\SelectionResource;
use App\Domains\Memora\Services\GuestSelectionService;
use App\Domains\Memora\Services\SelectionService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Selection Controller
 * 
 * Handles public/guest access to selections.
 * These endpoints are protected by guest token middleware (not user authentication).
 * Users must generate a guest token before accessing these routes.
 */
class PublicSelectionController extends Controller
{
    protected SelectionService $selectionService;
    protected GuestSelectionService $guestSelectionService;

    public function __construct(SelectionService $selectionService, GuestSelectionService $guestSelectionService)
    {
        $this->selectionService = $selectionService;
        $this->guestSelectionService = $guestSelectionService;
    }

    /**
     * Check selection status (public endpoint - no authentication required)
     * Returns status and ownership info for quick validation
     */
    public function checkStatus(Request $request, string $id): JsonResponse
    {
        try {
            $selection = MemoraSelection::query()
                ->where('uuid', $id)
                ->select('uuid', 'status', 'user_uuid', 'name')
                ->firstOrFail();

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $selection->user_uuid === $userUuid;
            }

            return ApiResponse::success([
                'id' => $selection->uuid,
                'status' => $selection->status->value,
                'name' => $selection->name,
                'isOwner' => $isOwner,
                'isAccessible' => in_array($selection->status->value, ['active', 'completed']) || ($selection->status->value === 'draft' && $isOwner),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Selection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to check selection status', [
                'selection_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to check selection status', 'CHECK_FAILED', 500);
        }
    }

    /**
     * Get a selection (protected by guest token)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify guest token exists
        if (!$guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        try {
            // For guest access, find the selection without user filtering
            $selection = MemoraSelection::query()
                ->where('uuid', $id)
                ->with(['mediaSets' => function ($query) {
                    $query->withCount('media')->orderBy('order');
                }])
                ->firstOrFail();
            
            // Allow access if selection status is 'active' or 'completed' (view-only for completed)
            if (!in_array($selection->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Selection is not accessible', 'SELECTION_NOT_ACCESSIBLE', 403);
            }
            
            return ApiResponse::success(new SelectionResource($selection));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Selection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch selection for guest', [
                'selection_id' => $id,
                'token_id' => $guestToken->uuid ?? null,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to fetch selection', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Verify password for a selection (public endpoint - no authentication required)
     * Used before generating guest token to verify password protection
     */
    public function verifyPassword(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        try {
            $selection = MemoraSelection::query()
                ->where('uuid', $id)
                ->select('uuid', 'password', 'status')
                ->firstOrFail();

            // Check if selection has password protection
            if (empty($selection->password)) {
                return ApiResponse::error('Selection does not have password protection', 'NO_PASSWORD', 400);
            }

            // Verify password (plain text comparison since passwords are stored in plain text)
            if ($selection->password !== $request->input('password')) {
                return ApiResponse::error('Incorrect password', 'INVALID_PASSWORD', 401);
            }

            // Check if selection is accessible
            if (!in_array($selection->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Selection is not accessible', 'SELECTION_NOT_ACCESSIBLE', 403);
            }

            return ApiResponse::success([
                'verified' => true,
                'message' => 'Password verified successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Selection not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify password', [
                'selection_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to verify password', 'VERIFY_FAILED', 500);
        }
    }

    /**
     * Get selected filenames (protected by guest token)
     */
    public function getSelectedFilenames(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify guest token exists
        if (!$guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        $setId = $request->query('setId');
        $result = $this->selectionService->getSelectedFilenames($id, $setId);

        return ApiResponse::success($result);
    }

    /**
     * Complete a selection (protected by guest token)
     * Accepts media UUIDs to mark as selected
     */
    public function complete(CompleteSelectionRequest $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this selection
        if ($guestToken->selection_uuid !== $id) {
            return ApiResponse::error('Token does not match selection', 'INVALID_TOKEN', 403);
        }

        // Complete selection with media UUIDs and guest email
        $selection = $this->selectionService->complete(
            $id,
            $request->validated()['mediaIds'],
            $guestToken->email
        );

        // Mark token as used
        $this->guestSelectionService->markTokenAsUsed($guestToken->token);

        return ApiResponse::success($selection);
    }
}

