<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Controllers\Controller;
use App\Domains\Memora\Models\MemoraCoverStyle;
use App\Domains\Memora\Resources\V1\CoverStyleResource;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CoverStyleController extends Controller
{
    /**
     * List active cover styles
     * GET /api/v1/cover-styles
     */
    public function index(Request $request): JsonResponse
    {
        $includeInactive = (bool) $request->query('include_inactive');
        $cacheKey = 'memora.cover_styles.'.($includeInactive ? 'all' : 'active');

        $coverStyles = Cache::remember($cacheKey, 300, function () use ($includeInactive) {
            $query = $includeInactive ? MemoraCoverStyle::ordered() : MemoraCoverStyle::active()->ordered();

            return $query->get();
        });

        return ApiResponse::success(CoverStyleResource::collection($coverStyles));
    }

    /**
     * Get a single cover style by UUID
     * GET /api/v1/cover-styles/:uuid
     */
    public function show(string $uuid): JsonResponse
    {
        $coverStyle = MemoraCoverStyle::findOrFail($uuid);

        return ApiResponse::success(new CoverStyleResource($coverStyle));
    }

    /**
     * Get a single cover style by slug
     * GET /api/v1/cover-styles/slug/:slug
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $coverStyle = MemoraCoverStyle::where('slug', $slug)->firstOrFail();

        return ApiResponse::success(new CoverStyleResource($coverStyle));
    }
}
