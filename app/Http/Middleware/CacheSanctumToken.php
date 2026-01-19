<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheSanctumToken
{
    /**
     * Handle an incoming request.
     * Cache Sanctum token lookups to reduce database queries.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip caching for public routes that use guest tokens
        if ($request->is('api/v1/public/*')) {
            return $next($request);
        }

        $token = $request->bearerToken();

        // Only cache if bearer token is present
        if ($token) {
            $tokenHash = hash('sha256', $token);
            $cacheKey = "sanctum_token:{$tokenHash}";

            try {
                // Use file cache store explicitly to avoid database connections
                $cachedData = Cache::store('file')->get($cacheKey);

                if ($cachedData !== null) {
                    if ($cachedData === false) {
                        // Token was previously invalid, cache for 5 seconds
                        Cache::store('file')->put($cacheKey, false, 5);
                        // Return 401 immediately for invalid tokens
                        return response()->json([
                            'message' => 'Unauthenticated.',
                            'status' => 401,
                            'code' => 'UNAUTHENTICATED',
                        ], 401);
                    } else {
                        // Token is valid, unserialize cached user object
                        $user = unserialize($cachedData);
                        if ($user instanceof \App\Models\User) {
                            $request->setUserResolver(function () use ($user) {
                                return $user;
                            });
                            // Mark that we used cached user to skip Sanctum's database query
                            $request->attributes->set('_sanctum_cached', true);
                        }
                    }
                } else {
                    // Token not in cache, let Sanctum handle it
                    // We'll cache the result after Sanctum processes the request
                    $request->attributes->set('_sanctum_token_hash', $tokenHash);
                }
            } catch (\Exception $e) {
                // If cache fails, just continue without caching (fail gracefully)
                // Log error but don't break the request
                \Illuminate\Support\Facades\Log::warning('Token cache failed', [
                    'error' => $e->getMessage(),
                ]);
                $request->attributes->set('_sanctum_token_hash', $tokenHash);
            }
        }

        $response = $next($request);

        // Cache the result after Sanctum processes the request
        if ($token && $request->attributes->has('_sanctum_token_hash')) {
            $tokenHash = $request->attributes->get('_sanctum_token_hash');
            $cacheKey = "sanctum_token:{$tokenHash}";

            try {
                if ($request->user()) {
                    // Token is valid, cache serialized user object for 60 seconds
                    $user = $request->user();
                    Cache::store('file')->put($cacheKey, serialize($user), 60);
                } else {
                    // Token is invalid, cache false for 5 seconds
                    Cache::store('file')->put($cacheKey, false, 5);
                }
            } catch (\Exception $e) {
                // If cache write fails, log but don't break the response
                \Illuminate\Support\Facades\Log::warning('Token cache write failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}
