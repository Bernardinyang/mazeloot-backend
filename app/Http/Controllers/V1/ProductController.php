<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Get all products (including inactive for "Coming Soon" display).
     * Returns all products so product selection page can show disabled/coming soon products.
     */
    public function index(): JsonResponse
    {
        $products = Cache::remember('api.products.index', 300, fn () => $this->productService->getAllProducts());

        return ApiResponse::success($products);
    }

    /**
     * Get product by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Cache::remember("api.products.show:{$slug}", 300, fn () => $this->productService->getProductBySlug($slug));

        if (! $product) {
            return ApiResponse::errorNotFound('Product not found');
        }

        return ApiResponse::success($product);
    }
}
