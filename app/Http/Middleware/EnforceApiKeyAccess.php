<?php

namespace App\Http\Middleware;

use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiKeyAccess
{
    /**
     * Handle an incoming request.
     * Ensures that API key authenticated requests have proper access
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if request is authenticated via API key
        $apiKey = $request->header('X-API-Key');

        if ($apiKey && ! $request->user()) {
            return ApiResponse::error(
                'API key authentication failed',
                'API_KEY_AUTH_FAILED',
                401
            );
        }

        return $next($request);
    }
}
