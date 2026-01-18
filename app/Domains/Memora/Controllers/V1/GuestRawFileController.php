<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Services\GuestRawFileService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestRawFileController extends Controller
{
    protected GuestRawFileService $guestRawFileService;

    public function __construct(GuestRawFileService $guestRawFileService)
    {
        $this->guestRawFileService = $guestRawFileService;
    }

    /**
     * Generate guest token for a raw file
     */
    public function generateToken(Request $request, string $rawFileId): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $token = $this->guestRawFileService->generateToken($rawFileId, $request->input('email'));

        return ApiResponse::success([
            'token' => $token->token,
            'expiresAt' => $token->expires_at->toIso8601String(),
            'email' => $token->email,
        ], 201);
    }
}
