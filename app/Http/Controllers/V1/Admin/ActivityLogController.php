<?php

namespace App\Http\Controllers\V1\Admin;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Pagination\PaginationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function __construct(
        protected PaginationService $paginationService
    ) {}

    /**
     * Get all user activity logs (for regular users only).
     */
    public function getUserActivityLogs(Request $request): JsonResponse
    {
        $query = ActivityLog::with(['user:id,uuid,email,first_name,last_name,role'])
            ->whereHas('user', function ($q) {
                $q->where('role', UserRoleEnum::USER);
            });

        // Filter by user
        if ($request->has('user_uuid')) {
            $query->where('user_uuid', $request->query('user_uuid'));
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->query('action'));
        }

        // Filter by subject type
        if ($request->has('subject_type')) {
            $query->where('subject_type', $request->query('subject_type'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->query('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->query('end_date'));
        }

        // Search by description
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where('description', 'like', "%{$search}%");
        }

        $perPage = $request->query('per_page', 50);
        $paginator = $this->paginationService->paginate($query->orderByDesc('created_at'), $perPage);

        $formattedData = $paginator->getCollection()->map(function ($log) {
            return [
                'uuid' => $log->uuid,
                'user' => $log->user ? [
                    'uuid' => $log->user->uuid,
                    'email' => $log->user->email,
                    'name' => trim(($log->user->first_name ?? '').' '.($log->user->last_name ?? '')),
                ] : null,
                'action' => $log->action,
                'description' => $log->description,
                'properties' => $log->properties,
                'route' => $log->route,
                'method' => $log->method,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        return ApiResponse::successOk($this->paginationService->formatResponse(
            $paginator->setCollection($formattedData)
        ));
    }

    /**
     * Get all admin activity logs (for admins and super admins).
     */
    public function getAdminActivityLogs(Request $request): JsonResponse
    {
        $query = ActivityLog::with(['user:id,uuid,email,first_name,last_name,role'])
            ->whereHas('user', function ($q) {
                $q->whereIn('role', [UserRoleEnum::ADMIN, UserRoleEnum::SUPER_ADMIN]);
            });

        // Filter by user
        if ($request->has('user_uuid')) {
            $query->where('user_uuid', $request->query('user_uuid'));
        }

        // Filter by role
        if ($request->has('role')) {
            $role = $request->query('role');
            if (in_array($role, [UserRoleEnum::ADMIN->value, UserRoleEnum::SUPER_ADMIN->value])) {
                $query->whereHas('user', function ($q) use ($role) {
                    $q->where('role', $role);
                });
            }
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->query('action'));
        }

        // Filter by subject type
        if ($request->has('subject_type')) {
            $query->where('subject_type', $request->query('subject_type'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->query('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->query('end_date'));
        }

        // Search by description
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where('description', 'like', "%{$search}%");
        }

        $perPage = $request->query('per_page', 50);
        $paginator = $this->paginationService->paginate($query->orderByDesc('created_at'), $perPage);

        $formattedData = $paginator->getCollection()->map(function ($log) {
            return [
                'uuid' => $log->uuid,
                'user' => $log->user ? [
                    'uuid' => $log->user->uuid,
                    'email' => $log->user->email,
                    'name' => trim(($log->user->first_name ?? '').' '.($log->user->last_name ?? '')),
                    'role' => $log->user->role->value,
                ] : null,
                'action' => $log->action,
                'description' => $log->description,
                'properties' => $log->properties,
                'route' => $log->route,
                'method' => $log->method,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        return ApiResponse::successOk($this->paginationService->formatResponse(
            $paginator->setCollection($formattedData)
        ));
    }

    /**
     * Get activity log statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $query = ActivityLog::query();

        // Filter by user role if specified
        if ($request->has('role')) {
            $role = $request->query('role');
            if ($role === 'user') {
                $query->whereHas('user', function ($q) {
                    $q->where('role', \App\Enums\UserRoleEnum::USER);
                });
            } elseif (in_array($role, ['admin', 'super_admin'])) {
                $query->whereHas('user', function ($q) use ($role) {
                    $roles = $role === 'admin'
                        ? [\App\Enums\UserRoleEnum::ADMIN]
                        : [\App\Enums\UserRoleEnum::ADMIN, \App\Enums\UserRoleEnum::SUPER_ADMIN];
                    $q->whereIn('role', $roles);
                });
            }
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->query('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->query('end_date'));
        }

        $total = $query->count();

        // Top actions
        $topActions = (clone $query)
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->mapWithKeys(fn ($item) => [$item->action => $item->count]);

        // Actions by subject type
        $bySubjectType = (clone $query)
            ->whereNotNull('subject_type')
            ->select('subject_type', DB::raw('count(*) as count'))
            ->groupBy('subject_type')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->subject_type => $item->count]);

        // Activity over time (last 30 days)
        $days = (int) $request->query('days', 30);
        $activityOverTime = (clone $query)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => $item->count,
            ]);

        return ApiResponse::successOk([
            'total' => $total,
            'top_actions' => $topActions,
            'by_subject_type' => $bySubjectType,
            'activity_over_time' => $activityOverTime,
        ]);
    }
}
