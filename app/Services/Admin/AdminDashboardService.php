<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\EarlyAccessUser;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductSelection;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    /**
     * Get overall dashboard statistics.
     *
     * @param  string|null  $productSlug
     * @return array
     */
    public function getDashboardStats(?string $productSlug = null): array
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::whereHas('status', function ($query) {
                $query->where('name', 'active');
            })->orWhereDoesntHave('status')->count(),
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'early_access_users' => EarlyAccessUser::where('is_active', true)->count(),
            'recent_activity' => ActivityLog::recent(7)->count(),
        ];

        if ($productSlug) {
            $product = Product::where('slug', $productSlug)->first();
            if ($product) {
                $stats['product_stats'] = $this->getProductStats($productSlug);
            }
        }

        return $stats;
    }

    /**
     * Get user statistics.
     *
     * @param  string|null  $productSlug
     * @return array
     */
    public function getUserStats(?string $productSlug = null): array
    {
        $query = User::query();

        if ($productSlug) {
            $product = Product::where('slug', $productSlug)->first();
            if ($product) {
                $query->whereHas('productSelections', function ($q) use ($product) {
                    $q->where('product_uuid', $product->uuid);
                });
            }
        }

        return [
            'total' => $query->count(),
            'by_role' => $query->select('role', DB::raw('count(*) as count'))
                ->groupBy('role')
                ->get()
                ->mapWithKeys(fn($item) => [$item->role->value => $item->count]),
            'with_early_access' => $query->whereHas('earlyAccess', function ($q) {
                $q->where('is_active', true);
            })->count(),
            'recent_registrations' => $query->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Get product-specific statistics.
     *
     * @param  string  $productSlug
     * @return array
     */
    public function getProductStats(string $productSlug): array
    {
        $product = Product::where('slug', $productSlug)->first();

        if (!$product) {
            return [];
        }

        $userCount = UserProductSelection::where('product_uuid', $product->uuid)
            ->select('user_uuid')
            ->distinct()
            ->count('user_uuid');

        return [
            'product' => [
                'uuid' => $product->uuid,
                'slug' => $product->slug,
                'name' => $product->name,
                'is_active' => $product->is_active,
            ],
            'user_count' => $userCount,
            'onboarding_completed' => $product->userOnboardingStatuses()
                ->whereNotNull('completed_at')
                ->select('user_uuid')
                ->distinct()
                ->count('user_uuid'),
            'onboarding_pending' => $product->userOnboardingStatuses()
                ->whereNull('completed_at')
                ->select('user_uuid')
                ->distinct()
                ->count('user_uuid'),
        ];
    }

    /**
     * Get early access statistics.
     *
     * @return array
     */
    public function getEarlyAccessStats(): array
    {
        $total = EarlyAccessUser::where('is_active', true)->count();
        $expired = EarlyAccessUser::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->count();
        $active = EarlyAccessUser::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'with_features' => EarlyAccessUser::where('is_active', true)
                ->whereJsonLength('feature_flags', '>', 0)
                ->count(),
            'with_discounts' => EarlyAccessUser::where('is_active', true)
                ->where('discount_percentage', '>', 0)
                ->count(),
        ];
    }

    /**
     * Get activity statistics.
     *
     * @param  string|null  $productSlug
     * @param  int|null  $days
     * @return array
     */
    public function getActivityStats(?string $productSlug = null, ?int $days = 30): array
    {
        $query = ActivityLog::query();

        if ($days) {
            $query->recent($days);
        }

        // Filter by product if specified (would need product info in activity logs)
        // For now, return general stats

        return [
            'total' => $query->count(),
            'by_action' => $query->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->mapWithKeys(fn($item) => [$item->action => $item->count]),
            'recent' => $query->orderByDesc('created_at')
                ->limit(20)
                ->with('user')
                ->get()
                ->map(fn($log) => [
                    'id' => $log->uuid,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user ? [
                        'uuid' => $log->user->uuid,
                        'name' => $log->user->first_name.' '.$log->user->last_name,
                        'email' => $log->user->email,
                    ] : null,
                    'created_at' => $log->created_at->toIso8601String(),
                ]),
        ];
    }
}
