<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService
    ) {}

    /**
     * Get overall dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $productSlug = $request->query('product');

        $stats = $this->dashboardService->getDashboardStats($productSlug);

        return ApiResponse::successOk($stats);
    }

    /**
     * Get product-specific statistics.
     */
    public function getProductStats(Request $request, string $productSlug): JsonResponse
    {
        $stats = $this->dashboardService->getProductStats($productSlug);

        if (empty($stats)) {
            return ApiResponse::errorNotFound('Product not found');
        }

        return ApiResponse::successOk($stats);
    }

    /**
     * Get user management statistics.
     */
    public function getUserStats(Request $request): JsonResponse
    {
        $productSlug = $request->query('product');

        $stats = $this->dashboardService->getUserStats($productSlug);

        return ApiResponse::successOk($stats);
    }
}
