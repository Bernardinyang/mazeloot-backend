<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Services\GuestRawFilesService;
use App\Services\Product\SubdomainResolutionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestRawFilesController extends Controller
{
    protected GuestRawFilesService $guestRawFilesService;
    protected SubdomainResolutionService $subdomainResolutionService;

    public function __construct(
        GuestRawFilesService $guestRawFilesService,
        SubdomainResolutionService $subdomainResolutionService
    ) {
        $this->guestRawFilesService = $guestRawFilesService;
        $this->subdomainResolutionService = $subdomainResolutionService;
    }

    /**
     * Generate guest token for raw files
     */
    public function generateToken(Request $request, string $subdomainOrUsername, string $rawFilesId): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            // Resolve user from subdomain/username
            $resolution = $this->subdomainResolutionService->resolve($subdomainOrUsername);
            $resolvedUser = $resolution['user'];

            if (!$resolvedUser) {
                return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
            }

            // Verify raw files belongs to this user
            $rawFiles = \App\Domains\Memora\Models\MemoraRawFiles::where('uuid', $rawFilesId)
                ->where('user_uuid', $resolvedUser->uuid)
                ->firstOrFail();

            $token = $this->guestRawFilesService->generateToken($rawFilesId, $request->input('email'));

            return ApiResponse::success([
                'token' => $token->token,
                'expiresAt' => $token->expires_at->toIso8601String(),
                'email' => $token->email,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Raw Files phase not found', 'NOT_FOUND', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'ACCESS_DENIED', 403);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate guest token for raw files', [
                'raw_files_id' => $rawFilesId,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to generate token', 'GENERATION_FAILED', 500);
        }
    }
}
