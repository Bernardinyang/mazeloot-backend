<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Resources\V1\ProofingResource;
use App\Domains\Memora\Services\GuestProofingService;
use App\Domains\Memora\Services\ProofingService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Proofing Controller
 *
 * Handles public/guest access to proofing.
 * These endpoints are protected by guest token middleware (not user authentication).
 * Users must generate a guest token before accessing these routes.
 */
class PublicProofingController extends Controller
{
    protected ProofingService $proofingService;

    protected GuestProofingService $guestProofingService;

    public function __construct(ProofingService $proofingService, GuestProofingService $guestProofingService)
    {
        $this->proofingService = $proofingService;
        $this->guestProofingService = $guestProofingService;
    }

    /**
     * Check proofing status (public endpoint - no authentication required)
     * Returns status and ownership info for quick validation
     */
    public function checkStatus(Request $request, string $id): JsonResponse
    {
        try {
            $proofing = MemoraProofing::query()
                ->where('uuid', $id)
                ->select('uuid', 'status', 'user_uuid', 'name')
                ->firstOrFail();

            $isOwner = false;
            if (auth()->check()) {
                $userUuid = auth()->user()->uuid;
                $isOwner = $proofing->user_uuid === $userUuid;
            }

            return ApiResponse::success([
                'id' => $proofing->uuid,
                'status' => $proofing->status->value,
                'name' => $proofing->name,
                'isOwner' => $isOwner,
                'isAccessible' => in_array($proofing->status->value, ['active', 'completed']) || ($proofing->status->value === 'draft' && $isOwner),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Proofing not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to check proofing status', [
                'proofing_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to check proofing status', 'CHECK_FAILED', 500);
        }
    }

    /**
     * Get a proofing (protected by guest token)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify guest token exists
        if (! $guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        try {
            // For guest access, find the proofing without user filtering
            $proofing = MemoraProofing::query()
                ->where('uuid', $id)
                ->with(['mediaSets' => function ($query) {
                    $query->withCount('media')->orderBy('order');
                }])
                ->firstOrFail();

            // Allow access if proofing status is 'active' or 'completed' (view-only for completed)
            if (! in_array($proofing->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
            }

            return ApiResponse::success(new ProofingResource($proofing));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Proofing not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch proofing for guest', [
                'proofing_id' => $id,
                'token_id' => $guestToken->uuid ?? null,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch proofing', 'FETCH_FAILED', 500);
        }
    }

    /**
     * Verify password for a proofing (public endpoint - no authentication required)
     * Used before generating guest token to verify password protection
     */
    public function verifyPassword(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        try {
            $proofing = MemoraProofing::query()
                ->where('uuid', $id)
                ->select('uuid', 'password', 'status')
                ->firstOrFail();

            // Check if proofing has password protection
            if (empty($proofing->password)) {
                return ApiResponse::error('Proofing does not have password protection', 'NO_PASSWORD', 400);
            }

            // Verify password (plain text comparison since passwords are stored in plain text)
            if ($proofing->password !== $request->input('password')) {
                return ApiResponse::error('Incorrect password', 'INVALID_PASSWORD', 401);
            }

            // Check if proofing is accessible
            if (! in_array($proofing->status->value, ['active', 'completed'])) {
                return ApiResponse::error('Proofing is not accessible', 'PROOFING_NOT_ACCESSIBLE', 403);
            }

            return ApiResponse::success([
                'verified' => true,
                'message' => 'Password verified successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Proofing not found', 'NOT_FOUND', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to verify password', [
                'proofing_id' => $id,
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
        if (! $guestToken) {
            return ApiResponse::error('Guest token is required', 'GUEST_TOKEN_MISSING', 401);
        }

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        $setId = $request->query('setId');
        $result = $this->proofingService->getSelectedFilenames($id, $setId);

        return ApiResponse::success($result);
    }

    /**
     * Complete a proofing (protected by guest token)
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $guestToken = $request->attributes->get('guest_token');

        // Verify the token belongs to this proofing
        if ($guestToken->proofing_uuid !== $id) {
            return ApiResponse::error('Token does not match proofing', 'INVALID_TOKEN', 403);
        }

        // Get project UUID from proofing
        $proofing = MemoraProofing::where('uuid', $id)->firstOrFail();
        if (! $proofing->project_uuid) {
            return ApiResponse::error('Proofing is not linked to a project', 'NO_PROJECT', 400);
        }

        // Complete proofing
        $completedProofing = $this->proofingService->complete($proofing->project_uuid, $id);

        // Mark token as used
        $this->guestProofingService->markTokenAsUsed($guestToken->token);

        return ApiResponse::success(new ProofingResource($completedProofing));
    }
}
