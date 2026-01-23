<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    /**
     * Get all active products ordered by order field.
     */
    public function getActiveProducts(): Collection
    {
        return Product::active()->ordered()->get();
    }

    /**
     * Get all products (including inactive) ordered by order field.
     * Used for product selection page to show "Coming Soon" products.
     */
    public function getAllProducts(): Collection
    {
        return Product::ordered()->get();
    }

    /**
     * Get product by slug.
     */
    public function getProductBySlug(string $slug): ?Product
    {
        return Product::where('slug', $slug)->first();
    }

    /**
     * Get user's selected products with product details.
     */
    public function getUserSelectedProducts(User $user): Collection
    {
        $selections = $user->productSelections()->with('product')->get();

        return $selections->map(function ($selection) {
            return $selection->product;
        })->filter();
    }
}
