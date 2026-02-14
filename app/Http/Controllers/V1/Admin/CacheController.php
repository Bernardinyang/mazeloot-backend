<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class CacheController extends Controller
{
    /**
     * Clear application cache (requires admin). Rate-limit in production.
     */
    public function clear(): JsonResponse
    {
        Artisan::call('cache:clear');
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'admin_cache_cleared',
                request()->user(),
                'Admin cleared application cache',
                [],
                request()->user(),
                request()
            );
        } catch (\Throwable $e) {
            // do not fail response
        }

        return ApiResponse::successOk([
            'message' => 'Application cache cleared',
        ]);
    }

    /**
     * Clear all caches (config, route, view, cache, compiled) then optimize (requires admin).
     */
    public function clearAllAndOptimize(): JsonResponse
    {
        Artisan::call('optimize:clear');
        Artisan::call('optimize');
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'admin_cache_optimized',
                request()->user(),
                'Admin cleared all caches and ran optimize',
                [],
                request()->user(),
                request()
            );
        } catch (\Throwable $e) {
            // do not fail response
        }

        return ApiResponse::successOk([
            'message' => 'All caches cleared and application optimized',
        ]);
    }
}
