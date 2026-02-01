<?php

namespace App\Http\Middleware;

use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotStarterPlan
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized();
        }

        $tier = $user->memora_tier ?? 'starter';
        if ($tier === 'starter') {
            return ApiResponse::errorForbidden('This feature is not available on the starter plan.');
        }

        return $next($request);
    }
}
