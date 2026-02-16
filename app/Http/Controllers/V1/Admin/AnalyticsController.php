<?php

namespace App\Http\Controllers\V1\Admin;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Admin\AdminDashboardService;
use App\Services\Pagination\PaginationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService,
        protected PaginationService $paginationService
    ) {}

    /**
     * Get activity logs.
     */
    public function getActivityLogs(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user');

        // Filter by product (if product info is stored in properties)
        if ($request->has('product')) {
            $productSlug = $request->query('product');
            $query->whereJsonContains('properties->product', $productSlug);
        }

        // Filter by user
        if ($request->has('user_uuid')) {
            $query->where('user_uuid', $request->query('user_uuid'));
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->query('action'));
        }

        // Date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->query('from'));
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->query('to'));
        }

        $perPage = $request->query('per_page', 50);
        $paginator = $this->paginationService->paginate($query->orderByDesc('created_at'), $perPage);

        $formatted = $paginator->getCollection()->map(fn ($log) => [
            'uuid' => $log->uuid,
            'action' => $log->action,
            'description' => $log->description,
            'user' => $log->user ? [
                'uuid' => $log->user->uuid,
                'email' => $log->user->email,
                'name' => $log->user->first_name.' '.$log->user->last_name,
            ] : null,
            'properties' => $log->properties,
            'route' => $log->route,
            'method' => $log->method,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at->toIso8601String(),
        ]);

        $paginator->setCollection($formatted);

        return ApiResponse::successOk($this->paginationService->formatResponse($paginator));
    }

    /**
     * Get user-specific activity.
     */
    public function getUserActivity(string $userUuid): JsonResponse
    {
        $stats = $this->dashboardService->getActivityStats(null, 30);

        $userLogs = ActivityLog::where('user_uuid', $userUuid)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'uuid' => $log->uuid,
                'action' => $log->action,
                'description' => $log->description,
                'properties' => $log->properties,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return ApiResponse::successOk([
            'user_uuid' => $userUuid,
            'recent_activity' => $userLogs,
        ]);
    }

    /**
     * Get product-specific activity.
     */
    public function getProductActivity(string $productSlug): JsonResponse
    {
        $stats = $this->dashboardService->getActivityStats($productSlug, 30);

        return ApiResponse::successOk($stats);
    }

    /**
     * Get full analytics overview (summary, users, activity, products, early access, etc.).
     * Query: days (default 30), or from + to (YYYY-MM-DD). compare=1 adds previous period.
     */
    public function getOverview(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $compare = $request->boolean('compare');
        $excludeSuperAdmin = ! (Auth::user()?->hasRole(UserRoleEnum::SUPER_ADMIN) ?? false);
        $cacheKey = 'admin.analytics.overview.'.md5(json_encode([$from, $to, $compare, $request->query('days'), $excludeSuperAdmin]));

        $data = Cache::remember($cacheKey, 300, function () use ($request, $from, $to, $compare, $excludeSuperAdmin) {
            $fromDate = null;
            $toDate = null;

            if ($from && $to) {
                $fromDate = \Carbon\Carbon::parse($from)->startOfDay();
                $toDate = \Carbon\Carbon::parse($to)->endOfDay();
                if ($fromDate->gt($toDate)) {
                    $fromDate = $toDate->copy()->startOfDay();
                    $toDate = $fromDate->copy()->endOfDay();
                }
                $days = (int) $fromDate->diffInDays($toDate) + 1;
                $days = min(max($days, 1), 365);
                $data = $this->dashboardService->getAnalyticsOverviewFromTo($fromDate, $toDate, false, $excludeSuperAdmin);
            } else {
                $days = (int) $request->query('days', 30);
                $days = min(max($days, 7), 90);
                $data = $this->dashboardService->getAnalyticsOverview($days, $excludeSuperAdmin);
            }

            if ($compare) {
                if ($fromDate && $toDate) {
                    $prevTo = $fromDate->copy()->subDay()->endOfDay();
                    $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();
                    $data['comparison'] = $this->dashboardService->getAnalyticsOverviewFromTo($prevFrom, $prevTo, true, $excludeSuperAdmin);
                } else {
                    $data['comparison'] = $this->dashboardService->getAnalyticsOverviewComparison($days, $excludeSuperAdmin);
                }
            }
            $revenueOverTime = $this->dashboardService->getRevenueOverTime();
            if ($revenueOverTime !== null) {
                $data['revenue_over_time'] = $revenueOverTime;
            }

            return $data;
        });

        return ApiResponse::successOk($data);
    }
}
