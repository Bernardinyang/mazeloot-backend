<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (! $apiKey) {
            return ApiResponse::error(
                'API key is required',
                'API_KEY_MISSING',
                401
            );
        }

        $key = ApiKey::where('key', $apiKey)->first();

        if (! $key || $key->isExpired()) {
            return ApiResponse::error(
                'Invalid or expired API key',
                'API_KEY_INVALID',
                401
            );
        }

        // Update last used timestamp
        $key->update(['last_used_at' => now()]);

        // Set the authenticated user
        $request->setUserResolver(function () use ($key) {
            return $key->user;
        });

        return $next($request);
    }
}
