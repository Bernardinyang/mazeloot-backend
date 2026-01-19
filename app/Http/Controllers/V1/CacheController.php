<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CacheController extends Controller
{
    /**
     * Clear all application caches
     * Protected by secret token in query parameter or header
     *
     * Usage:
     * GET /api/v1/cache/clear-all?token={secret_token}
     * or
     * GET /api/v1/cache/clear-all with header X-Cache-Token: {secret_token}
     *
     * Set CACHE_CLEAR_SECRET_TOKEN in .env file
     */
    public function clearAll(): JsonResponse
    {
        $secretToken = env('CACHE_CLEAR_SECRET_TOKEN', 'change-me-in-production');

        // Get token from query parameter or header
        $providedToken = request()->query('token') ?? request()->header('X-Cache-Token');

        if (! $providedToken || $providedToken !== $secretToken) {
            Log::warning('Unauthorized cache clear attempt', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return ApiResponse::error(
                'Unauthorized. Invalid or missing cache clear token.',
                'UNAUTHORIZED',
                401
            );
        }

        try {
            Log::info('Clearing all caches via API', [
                'ip' => request()->ip(),
            ]);

            // Clear all caches
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('event:clear');

            // Clear bootstrap cache files
            $bootstrapCachePath = base_path('bootstrap/cache');
            if (is_dir($bootstrapCachePath)) {
                $files = glob($bootstrapCachePath.'/*.php');
                foreach ($files as $file) {
                    if (is_file($file) && basename($file) !== '.gitignore') {
                        @unlink($file);
                    }
                }
            }

            // Restart queue workers
            Artisan::call('queue:restart');

            return ApiResponse::success([
                'message' => 'All caches cleared successfully',
                'cleared' => [
                    'application_cache',
                    'config_cache',
                    'route_cache',
                    'view_cache',
                    'event_cache',
                    'bootstrap_cache',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear caches', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to clear caches: '.$e->getMessage(),
                'CACHE_CLEAR_FAILED',
                500
            );
        }
    }
}
