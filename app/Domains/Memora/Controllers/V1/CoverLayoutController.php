<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Controllers\Controller;
use App\Domains\Memora\Models\MemoraCoverLayout;
use App\Domains\Memora\Resources\V1\CoverLayoutResource;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoverLayoutController extends Controller
{
    /**
     * List active cover layouts
     * GET /api/v1/cover-layouts
     */
    public function index(Request $request): JsonResponse
    {
        $query = MemoraCoverLayout::active()->ordered();

        // Optionally include inactive layouts if requested (for admin)
        if ($request->query('include_inactive')) {
            $query = MemoraCoverLayout::ordered();
        }

        $coverLayouts = $query->get();

        return ApiResponse::success(CoverLayoutResource::collection($coverLayouts));
    }

    /**
     * Get a single cover layout by UUID
     * GET /api/v1/cover-layouts/:uuid
     */
    public function show(string $uuid): JsonResponse
    {
        $coverLayout = MemoraCoverLayout::findOrFail($uuid);

        return ApiResponse::success(new CoverLayoutResource($coverLayout));
    }

    /**
     * Get a single cover layout by slug
     * GET /api/v1/cover-layouts/slug/:slug
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $coverLayout = MemoraCoverLayout::where('slug', $slug)->firstOrFail();

        return ApiResponse::success(new CoverLayoutResource($coverLayout));
    }
}
