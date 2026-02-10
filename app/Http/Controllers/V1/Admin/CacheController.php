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

        return ApiResponse::successOk([
            'message' => 'Application cache cleared',
        ]);
    }
}
