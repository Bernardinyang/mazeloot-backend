<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateProduct
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $productSlug = $request->route('productSlug');

        if (!$productSlug) {
            return ApiResponse::error(
                'Product slug is required',
                'PRODUCT_SLUG_MISSING',
                400
            );
        }

        // Find product by slug
        $product = Product::where('slug', $productSlug)->first();

        if (!$product) {
            return ApiResponse::error(
                'Product not found',
                'PRODUCT_NOT_FOUND',
                404
            );
        }

        if (!$product->is_active) {
            return ApiResponse::error(
                'Product is not active',
                'PRODUCT_NOT_ACTIVE',
                403
            );
        }

        // For authenticated routes, verify user has selected this product
        if ($request->user()) {
            $user = $request->user();
            $hasProduct = $user->productPreferences()
                ->where('product_uuid', $product->uuid)
                ->exists();

            if (!$hasProduct) {
                return ApiResponse::error(
                    'You have not selected this product',
                    'PRODUCT_NOT_SELECTED',
                    403
                );
            }
        }

        // Add product to request attributes for use in controllers
        $request->attributes->set('product', $product);

        return $next($request);
    }
}
