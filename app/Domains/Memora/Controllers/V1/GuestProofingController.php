<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Services\GuestProofingService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestProofingController extends Controller
{
    protected GuestProofingService $guestProofingService;

    public function __construct(GuestProofingService $guestProofingService)
    {
        $this->guestProofingService = $guestProofingService;
    }

    /**
     * Generate guest token for a proofing
     */
    public function generateToken(Request $request, string $proofingId): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $token = $this->guestProofingService->generateToken($proofingId, $request->input('email'));

        return ApiResponse::success([
            'token' => $token->token,
            'expiresAt' => $token->expires_at->toIso8601String(),
            'email' => $token->email,
        ], 201);
    }
}

