<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * List all active products
     */
    public function index(): JsonResponse
    {
        $products = Product::active()
            ->ordered()
            ->get();

        return ApiResponse::success(ProductResource::collection($products));
    }

    /**
     * Get product by slug
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->first();

        if (!$product) {
            return ApiResponse::error(
                'Product not found',
                'PRODUCT_NOT_FOUND',
                404
            );
        }

        return ApiResponse::success(new ProductResource($product));
    }
}
