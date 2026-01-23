<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

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
        $products = $this->productService->getAllProducts();

        return ApiResponse::success($products);
    }

    /**
     * Get product by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);

        if (! $product) {
            return ApiResponse::errorNotFound('Product not found');
        }

        return ApiResponse::success($product);
    }
}
