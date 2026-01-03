<?php

namespace App\Http\Middleware;

use App\Models\GuestCollectionToken;
use App\Models\GuestProofingToken;
use App\Models\GuestSelectionToken;
use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-Guest-Token') ?? $request->query('guest_token');

        if (! $token) {
            return ApiResponse::error(
                'Guest token is required',
                'GUEST_TOKEN_MISSING',
                401
            );
        }

        // Try to find selection token first
        $guestToken = GuestSelectionToken::where('token', $token)->first();

        // If not found, try proofing token
        if (! $guestToken) {
            $guestToken = GuestProofingToken::where('token', $token)->first();
        }

        // If not found, try collection token
        if (! $guestToken) {
            $guestToken = GuestCollectionToken::where('token', $token)->first();
        }

        if (! $guestToken) {
            return ApiResponse::error(
                'Invalid guest token',
                'GUEST_TOKEN_INVALID',
                401
            );
        }

        if ($guestToken->isExpired()) {
            return ApiResponse::error(
                'Guest token has expired',
                'GUEST_TOKEN_EXPIRED',
                401
            );
        }

        // Attach guest token to request for use in controllers
        $request->attributes->set('guest_token', $guestToken);

        return $next($request);
    }
}
