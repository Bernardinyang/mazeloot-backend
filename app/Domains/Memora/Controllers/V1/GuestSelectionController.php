<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Services\GuestSelectionService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestSelectionController extends Controller
{
    protected GuestSelectionService $guestSelectionService;

    public function __construct(GuestSelectionService $guestSelectionService)
    {
        $this->guestSelectionService = $guestSelectionService;
    }

    /**
     * Generate guest token for a selection
     */
    public function generateToken(Request $request, string $selectionId): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $token = $this->guestSelectionService->generateToken($selectionId, $request->input('email'));

        return ApiResponse::success([
            'token' => $token->token,
            'expiresAt' => $token->expires_at->toIso8601String(),
            'email' => $token->email,
        ], 201);
    }
}
