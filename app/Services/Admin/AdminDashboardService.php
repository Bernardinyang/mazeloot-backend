<?php

namespace App\Services\Admin;

use App\Domains\Memora\Models\MemoraSubscription;
use App\Models\ActivityLog;
use App\Models\ContactSubmission;
use App\Models\EarlyAccessUser;
use App\Models\Newsletter;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductSelection;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    /**
     * Get overall dashboard statistics.
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
                ->mapWithKeys(fn ($item) => [$item->role->value => $item->count]),
            'with_early_access' => $query->whereHas('earlyAccess', function ($q) {
                $q->where('is_active', true);
            })->count(),
            'recent_registrations' => $query->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Get product-specific statistics.
     */
    public function getProductStats(string $productSlug): array
    {
        $product = Product::where('slug', $productSlug)->first();

        if (! $product) {
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
            'by_action' => (clone $query)->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->mapWithKeys(fn ($item) => [$item->action => $item->count]),
            'recent' => (clone $query)->orderByDesc('created_at')
                ->limit(50)
                ->with('user')
                ->get()
                ->map(fn ($log) => [
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

    /**
     * Get analytics overview for admin dashboard (users, activity, products, etc.).
     */
    public function getAnalyticsOverview(int $days = 30): array
    {
        $dashboard = $this->getDashboardStats(null);
        $userStats = $this->getUserStats(null);
        $earlyAccess = $this->getEarlyAccessStats();
        $activityStats = $this->getActivityStats(null, $days);

        $usersByDay = User::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $activityByDay = ActivityLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $contactCount = ContactSubmission::count();
        $waitlistCount = Waitlist::count();
        $newsletterCount = Newsletter::count();

        $contactByDay = ContactSubmission::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $waitlistByDay = Waitlist::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $newsletterByDay = Newsletter::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $topUsersByActivity = ActivityLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('user_uuid')
            ->select('user_uuid', DB::raw('count(*) as count'))
            ->groupBy('user_uuid')
            ->orderByDesc('count')
            ->limit(15)
            ->with('user')
            ->get()
            ->map(fn ($row) => [
                'user_uuid' => $row->user_uuid,
                'activity_count' => $row->count,
                'user' => $row->user ? [
                    'uuid' => $row->user->uuid,
                    'email' => $row->user->email,
                    'name' => trim($row->user->first_name.' '.$row->user->last_name),
                ] : null,
            ])
            ->all();

        $products = Product::select('uuid', 'slug', 'name', 'is_active')->get();
        $productStats = $products->map(fn ($p) => $this->getProductStats($p->slug))->filter(fn ($s) => ! empty($s))->values()->all();

        $dates = collect(range(0, $days - 1))->map(fn ($i) => now()->subDays($days - 1 - $i)->format('Y-m-d'));
        $subscription = $this->getSubscriptionRevenue();
        $summary = [
            'users' => $dashboard['total_users'],
            'active_users' => $dashboard['active_users'],
            'products' => $dashboard['total_products'],
            'active_products' => $dashboard['active_products'],
            'early_access' => $dashboard['early_access_users'],
            'activity_7d' => $dashboard['recent_activity'],
            'contact_submissions' => $contactCount,
            'waitlist_entries' => $waitlistCount,
            'newsletter_subscribers' => $newsletterCount,
        ];
        if ($subscription !== null) {
            $summary['subscription'] = $subscription;
        }

        return [
            'summary' => $summary,
            'users' => [
                'total' => $userStats['total'],
                'by_role' => $userStats['by_role'],
                'with_early_access' => $userStats['with_early_access'],
                'recent_registrations_30d' => $userStats['recent_registrations'],
                'registrations_by_day' => $dates->mapWithKeys(fn ($d) => [$d => $usersByDay[$d] ?? 0])->all(),
            ],
            'activity' => [
                'total' => $activityStats['total'],
                'by_action' => $activityStats['by_action'],
                'events_by_day' => $dates->mapWithKeys(fn ($d) => [$d => $activityByDay[$d] ?? 0])->all(),
                'recent' => $activityStats['recent'],
                'top_users' => $topUsersByActivity,
            ],
            'contact' => [
                'total' => $contactCount,
                'by_day' => $dates->mapWithKeys(fn ($d) => [$d => $contactByDay[$d] ?? 0])->all(),
            ],
            'waitlist' => [
                'total' => $waitlistCount,
                'by_day' => $dates->mapWithKeys(fn ($d) => [$d => $waitlistByDay[$d] ?? 0])->all(),
            ],
            'newsletter' => [
                'total' => $newsletterCount,
                'by_day' => $dates->mapWithKeys(fn ($d) => [$d => $newsletterByDay[$d] ?? 0])->all(),
            ],
            'early_access' => $earlyAccess,
            'products' => $productStats,
            'subscription' => $subscription,
        ];
    }

    /**
     * Get analytics overview for a specific date range.
     */
    public function getAnalyticsOverviewFromTo(Carbon $from, Carbon $to, bool $summaryOnly = false): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $days = (int) $from->diffInDays($to) + 1;
        $dates = collect();
        for ($i = 0; $i < $days; $i++) {
            $dates->push($from->copy()->addDays($i)->format('Y-m-d'));
        }

        $usersInPeriod = User::whereBetween('created_at', [$from, $to])->count();
        $activityInPeriod = ActivityLog::whereBetween('created_at', [$from, $to])->count();
        $contactInPeriod = ContactSubmission::whereBetween('created_at', [$from, $to])->count();
        $waitlistInPeriod = Waitlist::whereBetween('created_at', [$from, $to])->count();
        $newsletterInPeriod = Newsletter::whereBetween('created_at', [$from, $to])->count();

        $usersByDay = User::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $activityByDay = ActivityLog::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $subscription = $this->getSubscriptionRevenue();
        $summary = [
            'users' => $usersInPeriod,
            'active_users' => $usersInPeriod,
            'products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'early_access' => EarlyAccessUser::where('is_active', true)->count(),
            'activity_7d' => $activityInPeriod,
            'contact_submissions' => $contactInPeriod,
            'waitlist_entries' => $waitlistInPeriod,
            'newsletter_subscribers' => $newsletterInPeriod,
        ];
        if ($subscription !== null) {
            $summary['subscription'] = $subscription;
        }

        if ($summaryOnly) {
            return ['summary' => $summary, 'from' => $from->toIso8601String(), 'to' => $to->toIso8601String()];
        }

        $contactByDay = ContactSubmission::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')->orderBy('date')->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);
        $waitlistByDay = Waitlist::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')->orderBy('date')->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);
        $newsletterByDay = Newsletter::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')->orderBy('date')->get()
            ->mapWithKeys(fn ($row) => [$row->date => $row->count]);

        $byAction = ActivityLog::whereBetween('created_at', [$from, $to])
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')->orderByDesc('count')->limit(10)->get()
            ->mapWithKeys(fn ($item) => [$item->action => $item->count]);

        $topUsers = ActivityLog::whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_uuid')
            ->select('user_uuid', DB::raw('count(*) as count'))
            ->groupBy('user_uuid')->orderByDesc('count')->limit(15)
            ->with('user')->get()
            ->map(fn ($row) => [
                'user_uuid' => $row->user_uuid,
                'activity_count' => $row->count,
                'user' => $row->user ? [
                    'uuid' => $row->user->uuid,
                    'email' => $row->user->email,
                    'name' => trim($row->user->first_name.' '.$row->user->last_name),
                ] : null,
            ])->all();

        $recent = ActivityLog::whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')->limit(50)->with('user')->get()
            ->map(fn ($log) => [
                'id' => $log->uuid,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user ? [
                    'uuid' => $log->user->uuid,
                    'name' => $log->user->first_name.' '.$log->user->last_name,
                    'email' => $log->user->email,
                ] : null,
                'created_at' => $log->created_at->toIso8601String(),
            ])->all();

        $userStats = $this->getUserStats(null);
        $earlyAccess = $this->getEarlyAccessStats();
        $products = Product::select('uuid', 'slug', 'name', 'is_active')->get();
        $productStats = $products->map(fn ($p) => $this->getProductStats($p->slug))->filter(fn ($s) => ! empty($s))->values()->all();

        return [
            'summary' => $summary,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'users' => [
                'total' => $userStats['total'],
                'by_role' => $userStats['by_role'],
                'with_early_access' => $userStats['with_early_access'],
                'recent_registrations_30d' => $usersInPeriod,
                'registrations_by_day' => $dates->mapWithKeys(fn ($d) => [$d => $usersByDay[$d] ?? 0])->all(),
            ],
            'activity' => [
                'total' => $activityInPeriod,
                'by_action' => $byAction,
                'events_by_day' => $dates->mapWithKeys(fn ($d) => [$d => $activityByDay[$d] ?? 0])->all(),
                'recent' => $recent,
                'top_users' => $topUsers,
            ],
            'contact' => ['total' => $contactInPeriod, 'by_day' => $dates->mapWithKeys(fn ($d) => [$d => $contactByDay[$d] ?? 0])->all()],
            'waitlist' => ['total' => $waitlistInPeriod, 'by_day' => $dates->mapWithKeys(fn ($d) => [$d => $waitlistByDay[$d] ?? 0])->all()],
            'newsletter' => ['total' => $newsletterInPeriod, 'by_day' => $dates->mapWithKeys(fn ($d) => [$d => $newsletterByDay[$d] ?? 0])->all()],
            'early_access' => $earlyAccess,
            'products' => $productStats,
            'subscription' => $subscription,
        ];
    }

    /**
     * Get analytics for previous period (same length as current days) for comparison.
     */
    public function getAnalyticsOverviewComparison(int $days): array
    {
        $prevTo = now()->subDays($days)->endOfDay();
        $prevFrom = now()->subDays(2 * $days - 1)->startOfDay();

        return $this->getAnalyticsOverviewFromTo($prevFrom, $prevTo, true);
    }

    /**
     * Subscription/revenue stats (Memora). Returns null if table missing.
     */
    public function getSubscriptionRevenue(): ?array
    {
        if (! Schema::hasTable('memora_subscriptions')) {
            return null;
        }
        $active = MemoraSubscription::whereIn('status', ['active', 'trialing'])->get();
        $count = $active->count();
        $mrrCents = $active->sum(function ($s) {
            $monthly = $s->billing_cycle === 'annual' ? (int) round($s->amount / 12) : $s->amount;
            return $monthly;
        });
        return [
            'active_subscriptions' => $count,
            'mrr_cents' => $mrrCents,
            'mrr_formatted' => '$'.number_format($mrrCents / 100, 2),
        ];
    }
}
