<?php

namespace App\Http\Middleware;

use App\Services\Subscription\TierService;
use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemoraFeature
{
    public function __construct(
        protected TierService $tierService
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::errorUnauthorized();
        }

        $features = $this->tierService->getFeatures($user);
        if (! in_array($feature, $features, true)) {
            return ApiResponse::errorForbidden('This feature is not available on your plan.');
        }

        return $next($request);
    }
}
