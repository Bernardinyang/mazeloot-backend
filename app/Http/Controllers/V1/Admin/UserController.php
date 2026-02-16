<?php

namespace App\Http\Controllers\V1\Admin;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStatus;
use App\Services\Notification\NotificationService;
use App\Services\Pagination\PaginationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected PaginationService $paginationService,
        protected NotificationService $notificationService
    ) {}

    /**
     * List users with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['status', 'earlyAccess']);

        $isSuperAdmin = Auth::user()?->hasRole(UserRoleEnum::SUPER_ADMIN) ?? false;
        if (! $isSuperAdmin) {
            $query->where('role', '!=', UserRoleEnum::SUPER_ADMIN);
        }

        // Filter by role
        if ($request->has('role')) {
            $role = $request->query('role');
            if (! $isSuperAdmin && $role === UserRoleEnum::SUPER_ADMIN->value) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('role', $role);
            }
        }

        // Filter by product
        if ($request->has('product')) {
            $productSlug = $request->query('product');
            $product = \App\Models\Product::where('slug', $productSlug)->first();
            if ($product) {
                $query->whereHas('productSelections', function ($q) use ($product) {
                    $q->where('product_uuid', $product->uuid);
                });
            }
        }

        // Filter by early access
        if ($request->has('early_access')) {
            if ($request->query('early_access') === 'true') {
                $query->whereHas('earlyAccess', function ($q) {
                    $q->where('is_active', true);
                });
            } else {
                $query->whereDoesntHave('earlyAccess');
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 20);
        $paginator = $this->paginationService->paginate($query->orderByDesc('created_at'), $perPage);

        return ApiResponse::successOk($this->paginationService->formatResponse($paginator));
    }

    /**
     * Get user details.
     */
    public function show(string $uuid): JsonResponse
    {
        $user = User::with(['status', 'earlyAccess', 'productSelections.product', 'pushSubscriptions'])
            ->find($uuid);

        if (! $user) {
            return ApiResponse::errorNotFound('User not found');
        }

        $isSuperAdmin = Auth::user()?->hasRole(UserRoleEnum::SUPER_ADMIN) ?? false;
        if (! $isSuperAdmin && $user->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            return ApiResponse::errorNotFound('User not found');
        }

        return ApiResponse::successOk([
            'uuid' => $user->uuid,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->role->value,
            'status' => $user->status ? [
                'uuid' => $user->status->uuid,
                'name' => $user->status->name,
                'description' => $user->status->description,
                'color' => $user->status->color,
            ] : null,
            'early_access' => $user->earlyAccess ? [
                'uuid' => $user->earlyAccess->uuid,
                'is_active' => $user->earlyAccess->isActive(),
                'discount_percentage' => $user->earlyAccess->discount_percentage,
                'feature_flags' => $user->earlyAccess->feature_flags ?? [],
                'release_version' => $user->earlyAccess->release_version,
                'expires_at' => $user->earlyAccess->expires_at?->toIso8601String(),
            ] : null,
            'product_selections' => $user->productSelections->map(fn ($selection) => [
                'product' => [
                    'uuid' => $selection->product->uuid,
                    'slug' => $selection->product->slug,
                    'name' => $selection->product->name,
                ],
                'selected_at' => $selection->selected_at?->toIso8601String(),
            ]),
            'push_subscriptions' => $user->pushSubscriptions->map(fn ($sub) => [
                'id' => $sub->id,
                'endpoint_hint' => strlen($sub->endpoint) > 48 ? '…'.substr($sub->endpoint, -48) : $sub->endpoint,
                'created_at' => $sub->created_at->toIso8601String(),
            ]),
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }

    /**
     * Update user.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = User::find($uuid);

        if (! $user) {
            return ApiResponse::errorNotFound('User not found');
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->uuid, 'uuid')],
            'status_uuid' => 'sometimes|nullable|exists:user_statuses,uuid',
        ]);

        $user->update($validated);

        // Log activity for user update
        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
            action: 'admin_user_updated',
            subject: $user,
            description: "Admin updated user {$user->email}.",
            properties: [
                'user_uuid' => $user->uuid,
                'user_email' => $user->email,
                'updated_fields' => array_keys($validated),
            ],
            causer: Auth::user()
        );

        return ApiResponse::successOk([
            'message' => 'User updated successfully',
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
        ]);
    }

    /**
     * Update user role (super admin only).
     */
    public function updateRole(Request $request, string $uuid): JsonResponse
    {
        $user = User::find($uuid);

        if (! $user) {
            return ApiResponse::errorNotFound('User not found');
        }

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRoleEnum::class)],
        ]);

        $oldRole = $user->role->value;
        $user->update(['role' => $validated['role']]);

        // Log activity for user role update
        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
            action: 'admin_user_role_updated',
            subject: $user,
            description: "Admin updated user {$user->email} role from {$oldRole} to {$validated['role']->value}.",
            properties: [
                'user_uuid' => $user->uuid,
                'user_email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $validated['role']->value,
            ],
            causer: Auth::user()
        );

        $this->notifyOtherAdmins(
            'user_role_updated',
            'User role updated',
            "User {$user->first_name} {$user->last_name} ({$user->email}) role changed from {$oldRole} to {$validated['role']->value}.",
            "/admin/users/{$user->uuid}",
            ['user_uuid' => $user->uuid, 'user_email' => $user->email]
        );

        return ApiResponse::successOk([
            'message' => 'User role updated successfully',
            'user' => [
                'uuid' => $user->uuid,
                'role' => $user->role->value,
            ],
        ]);
    }

    /**
     * Suspend user.
     */
    public function suspend(Request $request, string $uuid): JsonResponse
    {
        $user = User::find($uuid);

        if (! $user) {
            return ApiResponse::errorNotFound('User not found');
        }

        $isSuperAdmin = Auth::user()?->hasRole(UserRoleEnum::SUPER_ADMIN) ?? false;
        if (! $isSuperAdmin && $user->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            return ApiResponse::errorForbidden('Cannot suspend a super admin user.');
        }

        $validated = $request->validate([
            'reason' => 'sometimes|nullable|string|max:2000',
        ]);
        $reason = $validated['reason'] ?? null;

        $suspendedStatus = UserStatus::where('name', 'suspended')->first();
        if ($suspendedStatus) {
            $user->update(['status_uuid' => $suspendedStatus->uuid]);
        }

        // Log activity for user suspension
        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
            action: 'admin_user_suspended',
            subject: $user,
            description: "Admin suspended user {$user->email}.",
            properties: [
                'user_uuid' => $user->uuid,
                'user_email' => $user->email,
                'reason' => $reason,
            ],
            causer: Auth::user()
        );

        $user->notify(new \App\Notifications\UserSuspendedNotification($reason));

        $this->notifyOtherAdmins(
            'user_suspended',
            'User suspended',
            "User {$user->first_name} {$user->last_name} ({$user->email}) was suspended.",
            "/admin/users/{$user->uuid}",
            ['user_uuid' => $user->uuid, 'user_email' => $user->email]
        );

        return ApiResponse::successOk([
            'message' => 'User suspended successfully',
        ]);
    }

    /**
     * Activate user.
     */
    public function activate(Request $request, string $uuid): JsonResponse
    {
        $user = User::find($uuid);

        if (! $user) {
            return ApiResponse::errorNotFound('User not found');
        }

        $isSuperAdmin = Auth::user()?->hasRole(UserRoleEnum::SUPER_ADMIN) ?? false;
        if (! $isSuperAdmin && $user->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            return ApiResponse::errorForbidden('Cannot activate a super admin user.');
        }

        $activeStatus = UserStatus::where('name', 'active')->first();
        if ($activeStatus) {
            $user->update(['status_uuid' => $activeStatus->uuid]);
        } else {
            $user->update(['status_uuid' => null]);
        }

        $user->notify(new \App\Notifications\UserActivatedNotification());

        // Log activity for user activation
        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
            action: 'admin_user_activated',
            subject: $user,
            description: "Admin activated user {$user->email}.",
            properties: [
                'user_uuid' => $user->uuid,
                'user_email' => $user->email,
            ],
            causer: Auth::user()
        );

        $this->notifyOtherAdmins(
            'user_activated',
            'User activated',
            "User {$user->first_name} {$user->last_name} ({$user->email}) was activated.",
            "/admin/users/{$user->uuid}",
            ['user_uuid' => $user->uuid, 'user_email' => $user->email]
        );

        return ApiResponse::successOk([
            'message' => 'User activated successfully',
        ]);
    }

    /**
     * Notify all admins/super_admins except the current actor (in-app, product general).
     */
    private function notifyOtherAdmins(string $type, string $title, string $message, string $actionUrl, array $metadata = []): void
    {
        $currentUuid = Auth::id();
        $adminUuids = User::whereIn('role', [UserRoleEnum::ADMIN, UserRoleEnum::SUPER_ADMIN])
            ->where('uuid', '!=', $currentUuid)
            ->pluck('uuid')
            ->toArray();

        foreach ($adminUuids as $adminUuid) {
            $this->notificationService->create(
                $adminUuid,
                'general',
                $type,
                $title,
                $message,
                null,
                null,
                $actionUrl,
                $metadata
            );
        }
    }
}
