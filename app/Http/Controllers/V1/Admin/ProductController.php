<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AdminDashboardService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService
    ) {}

    /**
     * List all products.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::ordered()->get()->map(fn ($product) => [
            'uuid' => $product->uuid,
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'icon' => $product->icon,
            'is_active' => $product->is_active,
            'order' => $product->order,
        ]);

        return ApiResponse::successOk($products);
    }

    /**
     * Get product details.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->first();

        if (! $product) {
            return ApiResponse::errorNotFound('Product not found');
        }

        return ApiResponse::successOk([
            'uuid' => $product->uuid,
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'icon' => $product->icon,
            'is_active' => $product->is_active,
            'order' => $product->order,
            'metadata' => $product->metadata,
        ]);
    }

    /**
     * Update product.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->first();

        if (! $product) {
            return ApiResponse::errorNotFound('Product not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'icon' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
            'metadata' => 'sometimes|array',
        ]);

        $product->update($validated);

        // Log activity for product update
        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
            action: 'admin_product_updated',
            subject: $product,
            description: "Admin updated product '{$product->name}'.",
            properties: [
                'product_uuid' => $product->uuid,
                'product_slug' => $product->slug,
                'product_name' => $product->name,
                'updated_fields' => array_keys($validated),
            ],
            causer: Auth::user()
        );

        return ApiResponse::successOk([
            'message' => 'Product updated successfully',
            'product' => [
                'uuid' => $product->uuid,
                'slug' => $product->slug,
                'name' => $product->name,
            ],
        ]);
    }

    /**
     * Get users for a product.
     */
    public function getProductUsers(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->first();

        if (! $product) {
            return ApiResponse::errorNotFound('Product not found');
        }

        $users = $product->userProductSelections()
            ->with('user')
            ->get()
            ->map(fn ($selection) => [
                'uuid' => $selection->user->uuid,
                'email' => $selection->user->email,
                'first_name' => $selection->user->first_name,
                'last_name' => $selection->user->last_name,
                'selected_at' => $selection->selected_at?->toIso8601String(),
            ]);

        return ApiResponse::successOk($users);
    }

    /**
     * Get product statistics.
     */
    public function getProductStats(string $slug): JsonResponse
    {
        $stats = $this->dashboardService->getProductStats($slug);

        if (empty($stats)) {
            return ApiResponse::errorNotFound('Product not found');
        }

        return ApiResponse::successOk($stats);
    }
}
