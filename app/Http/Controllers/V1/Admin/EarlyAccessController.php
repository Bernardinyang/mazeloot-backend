<?php

namespace App\Http\Controllers\V1\Admin;

use App\Enums\EarlyAccessRequestStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ApproveEarlyAccessRequest;
use App\Http\Requests\V1\Admin\RejectEarlyAccessRequest;
use App\Models\EarlyAccessRequest;
use App\Models\EarlyAccessUser;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\EarlyAccess\EarlyAccessFeatureService;
use App\Services\EarlyAccess\EarlyAccessRequestService;
use App\Services\EarlyAccess\EarlyAccessService;
use App\Services\Notification\NotificationService;
use App\Services\Pagination\PaginationService;
use App\Support\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EarlyAccessController extends Controller
{
    public function __construct(
        protected EarlyAccessService $earlyAccessService,
        protected EarlyAccessFeatureService $featureService,
        protected EarlyAccessRequestService $requestService,
        protected PaginationService $paginationService,
        protected NotificationService $notificationService,
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * List early access users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EarlyAccessUser::with('user');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by reward type
        if ($request->has('has_features')) {
            $query->whereJsonLength('feature_flags', '>', 0);
        }

        if ($request->has('has_discount')) {
            $query->where('discount_percentage', '>', 0);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 20);
        $paginator = $this->paginationService->paginate($query->orderByDesc('granted_at'), $perPage);

        $formatted = $paginator->getCollection()->map(fn ($ea) => [
            'uuid' => $ea->uuid,
            'user' => [
                'uuid' => $ea->user->uuid,
                'email' => $ea->user->email,
                'first_name' => $ea->user->first_name,
                'last_name' => $ea->user->last_name,
            ],
            'discount_percentage' => $ea->discount_percentage,
            'feature_flags' => $ea->feature_flags ?? [],
            'storage_multiplier' => $ea->storage_multiplier,
            'priority_support' => $ea->priority_support,
            'exclusive_badge' => $ea->exclusive_badge,
            'release_version' => $ea->release_version,
            'is_active' => $ea->isActive(),
            'expires_at' => $ea->expires_at?->toIso8601String(),
            'granted_at' => $ea->granted_at->toIso8601String(),
        ]);

        $paginator->setCollection($formatted);

        return ApiResponse::successOk($this->paginationService->formatResponse($paginator));
    }

    /**
     * Grant early access to user(s).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_uuid' => 'required_without:user_uuids|uuid|exists:users,uuid',
            'user_uuids' => 'required_without:user_uuid|array',
            'user_uuids.*' => 'uuid|exists:users,uuid',
            'discount_percentage' => 'sometimes|integer|min:0|max:100',
            'discount_rules' => 'sometimes|array',
            'feature_flags' => 'sometimes|array',
            'storage_multiplier' => 'sometimes|numeric|min:1.0',
            'priority_support' => 'sometimes|boolean',
            'exclusive_badge' => 'sometimes|boolean',
            'trial_extension_days' => 'sometimes|integer|min:0',
            'custom_branding_enabled' => 'sometimes|boolean',
            'release_version' => 'sometimes|string|max:255',
            'expires_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|string|max:1000',
        ]);

        $userUuids = $validated['user_uuids'] ?? [$validated['user_uuid']];
        $rewards = [
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'discount_rules' => $validated['discount_rules'] ?? null,
            'feature_flags' => $validated['feature_flags'] ?? [],
            'storage_multiplier' => $validated['storage_multiplier'] ?? 1.0,
            'priority_support' => $validated['priority_support'] ?? false,
            'exclusive_badge' => $validated['exclusive_badge'] ?? true,
            'trial_extension_days' => $validated['trial_extension_days'] ?? 0,
            'custom_branding_enabled' => $validated['custom_branding_enabled'] ?? false,
            'release_version' => $validated['release_version'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $expiresAt = isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;

        $granted = [];
        foreach ($userUuids as $userUuid) {
            $earlyAccess = $this->earlyAccessService->grantEarlyAccess($userUuid, $rewards, $expiresAt);
            $granted[] = $earlyAccess->uuid;
        }

        return ApiResponse::successCreated([
            'message' => 'Early access granted successfully',
            'granted' => $granted,
        ]);
    }

    /**
     * Get early access details.
     */
    public function show(string $uuid): JsonResponse
    {
        $earlyAccess = EarlyAccessUser::with('user')->find($uuid);

        if (! $earlyAccess) {
            return ApiResponse::errorNotFound('Early access record not found');
        }

        return ApiResponse::successOk([
            'uuid' => $earlyAccess->uuid,
            'user' => [
                'uuid' => $earlyAccess->user->uuid,
                'email' => $earlyAccess->user->email,
                'first_name' => $earlyAccess->user->first_name,
                'last_name' => $earlyAccess->user->last_name,
            ],
            'discount_percentage' => $earlyAccess->discount_percentage,
            'discount_rules' => $earlyAccess->discount_rules,
            'feature_flags' => $earlyAccess->feature_flags ?? [],
            'storage_multiplier' => $earlyAccess->storage_multiplier,
            'priority_support' => $earlyAccess->priority_support,
            'exclusive_badge' => $earlyAccess->exclusive_badge,
            'trial_extension_days' => $earlyAccess->trial_extension_days,
            'custom_branding_enabled' => $earlyAccess->custom_branding_enabled,
            'release_version' => $earlyAccess->release_version,
            'is_active' => $earlyAccess->isActive(),
            'granted_at' => $earlyAccess->granted_at->toIso8601String(),
            'expires_at' => $earlyAccess->expires_at?->toIso8601String(),
            'notes' => $earlyAccess->notes,
        ]);
    }

    /**
     * Update early access rewards.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $earlyAccess = EarlyAccessUser::find($uuid);

        if (! $earlyAccess) {
            return ApiResponse::errorNotFound('Early access record not found');
        }

        $validated = $request->validate([
            'discount_percentage' => 'sometimes|integer|min:0|max:100',
            'discount_rules' => 'sometimes|array',
            'feature_flags' => 'sometimes|array',
            'storage_multiplier' => 'sometimes|numeric|min:1.0',
            'priority_support' => 'sometimes|boolean',
            'exclusive_badge' => 'sometimes|boolean',
            'trial_extension_days' => 'sometimes|integer|min:0',
            'custom_branding_enabled' => 'sometimes|boolean',
            'release_version' => 'sometimes|nullable|string|max:255',
            'expires_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $updated = $this->earlyAccessService->updateRewards($earlyAccess->user_uuid, $validated);

        return ApiResponse::successOk([
            'message' => 'Early access updated successfully',
            'early_access' => [
                'uuid' => $updated->uuid,
            ],
        ]);
    }

    /**
     * Revoke early access.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $earlyAccess = EarlyAccessUser::find($uuid);

        if (! $earlyAccess) {
            return ApiResponse::errorNotFound('Early access record not found');
        }

        $this->earlyAccessService->revokeEarlyAccess($earlyAccess->user_uuid);

        return ApiResponse::successOk([
            'message' => 'Early access revoked successfully',
        ]);
    }

    /**
     * List early access requests.
     */
    public function listRequests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EarlyAccessRequest::class);

        $query = EarlyAccessRequest::with(['user', 'reviewer']);

        // Filter by status (show all by default, can filter by specific status)
        if ($request->has('status') && $request->query('status') !== 'all') {
            $status = EarlyAccessRequestStatusEnum::tryFrom($request->query('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 20);
        $paginator = $this->paginationService->paginate($query->orderByDesc('created_at'), $perPage);

        $formatted = $paginator->getCollection()->map(fn ($req) => [
            'uuid' => $req->uuid,
            'user' => [
                'uuid' => $req->user->uuid,
                'email' => $req->user->email,
                'first_name' => $req->user->first_name,
                'last_name' => $req->user->last_name,
            ],
            'reason' => $req->reason,
            'status' => $req->status->value,
            'reviewed_by' => $req->reviewer ? [
                'uuid' => $req->reviewer->uuid,
                'email' => $req->reviewer->email,
                'first_name' => $req->reviewer->first_name,
                'last_name' => $req->reviewer->last_name,
            ] : null,
            'reviewed_at' => $req->reviewed_at?->toIso8601String(),
            'rejection_reason' => $req->rejection_reason,
            'created_at' => $req->created_at->toIso8601String(),
        ]);

        $paginator->setCollection($formatted);

        return ApiResponse::successOk($this->paginationService->formatResponse($paginator));
    }

    /**
     * Get early access request details.
     */
    public function showRequest(string $uuid): JsonResponse
    {
        $request = EarlyAccessRequest::with(['user', 'reviewer'])->find($uuid);

        if (! $request) {
            return ApiResponse::error('Early access request not found', 'REQUEST_NOT_FOUND', 404, [
                'request_uuid' => $uuid,
            ]);
        }

        $this->authorize('view', $request);

        return ApiResponse::successOk([
            'uuid' => $request->uuid,
            'user' => [
                'uuid' => $request->user->uuid,
                'email' => $request->user->email,
                'first_name' => $request->user->first_name,
                'last_name' => $request->user->last_name,
            ],
            'reason' => $request->reason,
            'status' => $request->status->value,
            'reviewed_by' => $request->reviewer ? [
                'uuid' => $request->reviewer->uuid,
                'email' => $request->reviewer->email,
                'first_name' => $request->reviewer->first_name,
                'last_name' => $request->reviewer->last_name,
            ] : null,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'rejection_reason' => $request->rejection_reason,
            'created_at' => $request->created_at->toIso8601String(),
        ]);
    }

    /**
     * Approve early access request with rewards.
     */
    public function approveRequest(ApproveEarlyAccessRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Lock the request record to prevent race conditions
            $requestRecord = EarlyAccessRequest::with('user')
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if (! $requestRecord) {
                return ApiResponse::error('Early access request not found', 'REQUEST_NOT_FOUND', 404, [
                    'request_uuid' => $uuid,
                ]);
            }

            $admin = auth()->user();
            $this->authorize('approve', $requestRecord);

            if ($requestRecord->status !== EarlyAccessRequestStatusEnum::PENDING) {
                return ApiResponse::error('Request has already been processed', 'ALREADY_PROCESSED', 400, [
                    'request_uuid' => $uuid,
                    'current_status' => $requestRecord->status->value,
                ]);
            }

            // Prepare rewards
            $rewards = [
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'discount_rules' => $validated['discount_rules'] ?? null,
                'feature_flags' => $validated['feature_flags'] ?? [],
                'storage_multiplier' => $validated['storage_multiplier'] ?? 1.0,
                'priority_support' => $validated['priority_support'] ?? false,
                'exclusive_badge' => $validated['exclusive_badge'] ?? true,
                'trial_extension_days' => $validated['trial_extension_days'] ?? 0,
                'custom_branding_enabled' => $validated['custom_branding_enabled'] ?? false,
                'release_version' => $validated['release_version'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            $expiresAt = isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;
            $earlyAccess = $this->requestService->approveRequest($requestRecord, $admin, $rewards, $expiresAt, $request);

            return ApiResponse::successOk([
                'message' => 'Early access request approved and granted successfully',
                'early_access' => [
                    'uuid' => $earlyAccess->uuid,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to approve early access request', [
                'request_uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to approve early access request. Please try again later.',
                'APPROVAL_FAILED',
                500,
                ['request_uuid' => $uuid]
            );
        }
    }

    /**
     * Reject early access request.
     */
    public function rejectRequest(RejectEarlyAccessRequest $request, string $uuid): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Lock the request record to prevent race conditions
            $requestRecord = EarlyAccessRequest::with('user')
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if (! $requestRecord) {
                return ApiResponse::error('Early access request not found', 'REQUEST_NOT_FOUND', 404, [
                    'request_uuid' => $uuid,
                ]);
            }

            $admin = auth()->user();
            $this->authorize('reject', $requestRecord);

            if ($requestRecord->status !== EarlyAccessRequestStatusEnum::PENDING) {
                return ApiResponse::error('Request has already been processed', 'ALREADY_PROCESSED', 400, [
                    'request_uuid' => $uuid,
                    'current_status' => $requestRecord->status->value,
                ]);
            }

            $this->requestService->rejectRequest(
                $requestRecord,
                $admin,
                $validated['rejection_reason'] ?? null,
                $request
            );

            return ApiResponse::successOk([
                'message' => 'Early access request rejected successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to reject early access request', [
                'request_uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to reject early access request. Please try again later.',
                'REJECTION_FAILED',
                500,
                ['request_uuid' => $uuid]
            );
        }
    }

    /**
     * Bulk approve early access requests.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_uuids' => 'required|array|min:1',
            'request_uuids.*' => 'required|uuid|exists:early_access_requests,uuid',
            'discount_percentage' => 'sometimes|integer|min:0|max:100',
            'discount_rules' => 'sometimes|array',
            'feature_flags' => 'sometimes|array',
            'storage_multiplier' => 'sometimes|numeric|min:1.0',
            'priority_support' => 'sometimes|boolean',
            'exclusive_badge' => 'sometimes|boolean',
            'trial_extension_days' => 'sometimes|integer|min:0',
            'custom_branding_enabled' => 'sometimes|boolean',
            'release_version' => 'sometimes|string|max:255',
            'expires_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|string|max:1000',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $admin = auth()->user();
                $approved = [];
                $failed = [];

                $rewards = [
                    'discount_percentage' => $validated['discount_percentage'] ?? 0,
                    'discount_rules' => $validated['discount_rules'] ?? null,
                    'feature_flags' => $validated['feature_flags'] ?? [],
                    'storage_multiplier' => $validated['storage_multiplier'] ?? 1.0,
                    'priority_support' => $validated['priority_support'] ?? false,
                    'exclusive_badge' => $validated['exclusive_badge'] ?? true,
                    'trial_extension_days' => $validated['trial_extension_days'] ?? 0,
                    'custom_branding_enabled' => $validated['custom_branding_enabled'] ?? false,
                    'release_version' => $validated['release_version'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ];

                $expiresAt = isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;

                foreach ($validated['request_uuids'] as $uuid) {
                    $requestRecord = EarlyAccessRequest::with('user')
                        ->where('uuid', $uuid)
                        ->lockForUpdate()
                        ->first();

                    if (! $requestRecord || $requestRecord->status !== EarlyAccessRequestStatusEnum::PENDING) {
                        $failed[] = $uuid;

                        continue;
                    }

                    $requestRecord->update([
                        'status' => EarlyAccessRequestStatusEnum::APPROVED,
                        'reviewed_by' => $admin->uuid,
                        'reviewed_at' => now(),
                    ]);

                    $earlyAccess = $this->earlyAccessService->grantEarlyAccess($requestRecord->user_uuid, $rewards, $expiresAt);

                    $this->notificationService->create(
                        $requestRecord->user_uuid,
                        'system',
                        'early_access_approved',
                        'Early Access Approved',
                        'Your early access request has been approved!',
                        'You now have early access to exclusive features and benefits.',
                        null,
                        '/overview',
                        ['early_access_uuid' => $earlyAccess->uuid]
                    );

                    $approved[] = $uuid;
                }

                return ApiResponse::successOk([
                    'message' => 'Approved '.count($approved).' request(s), '.count($failed).' failed',
                    'approved' => $approved,
                    'failed' => $failed,
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Failed to bulk approve early access requests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to bulk approve requests. Please try again later.',
                'BULK_APPROVAL_FAILED',
                500
            );
        }
    }

    /**
     * Bulk reject early access requests.
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_uuids' => 'required|array|min:1',
            'request_uuids.*' => 'required|uuid|exists:early_access_requests,uuid',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $admin = auth()->user();
                $rejected = [];
                $failed = [];

                foreach ($validated['request_uuids'] as $uuid) {
                    $requestRecord = EarlyAccessRequest::with('user')
                        ->where('uuid', $uuid)
                        ->lockForUpdate()
                        ->first();

                    if (! $requestRecord || $requestRecord->status !== EarlyAccessRequestStatusEnum::PENDING) {
                        $failed[] = $uuid;

                        continue;
                    }

                    $requestRecord->update([
                        'status' => EarlyAccessRequestStatusEnum::REJECTED,
                        'reviewed_by' => $admin->uuid,
                        'reviewed_at' => now(),
                        'rejection_reason' => $validated['rejection_reason'] ?? null,
                    ]);

                    $this->notificationService->create(
                        $requestRecord->user_uuid,
                        'system',
                        'early_access_rejected',
                        'Early Access Request Rejected',
                        'Your early access request has been rejected.',
                        $validated['rejection_reason'] ?? 'We appreciate your interest. Please try again later.',
                        null,
                        '/early-access',
                        []
                    );

                    $rejected[] = $uuid;
                }

                return ApiResponse::successOk([
                    'message' => 'Rejected '.count($rejected).' request(s), '.count($failed).' failed',
                    'rejected' => $rejected,
                    'failed' => $failed,
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Failed to bulk reject early access requests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to bulk reject requests. Please try again later.',
                'BULK_REJECTION_FAILED',
                500
            );
        }
    }

    /**
     * Rollout feature to early access users.
     */
    public function rolloutFeature(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => 'required|string|max:255',
            'user_uuids' => 'sometimes|array',
            'user_uuids.*' => 'uuid|exists:users,uuid',
            'percentage' => 'sometimes|integer|min:0|max:100',
        ]);

        if (isset($validated['user_uuids'])) {
            $updated = 0;
            foreach ($validated['user_uuids'] as $userUuid) {
                if ($this->featureService->grantFeature($userUuid, $validated['feature'])) {
                    $updated++;
                }
            }

            return ApiResponse::successOk([
                'message' => "Feature rolled out to {$updated} user(s)",
                'updated' => $updated,
            ]);
        }

        if (isset($validated['percentage'])) {
            $updated = $this->featureService->rolloutPercentage($validated['feature'], $validated['percentage']);

            return ApiResponse::successOk([
                'message' => "Feature rolled out to {$updated} user(s)",
                'updated' => $updated,
            ]);
        }

        $updated = $this->featureService->grantToAll($validated['feature']);

        return ApiResponse::successOk([
            'message' => "Feature rolled out to {$updated} user(s)",
            'updated' => $updated,
        ]);
    }

    /**
     * Update release version for all early access users.
     */
    public function updateReleaseVersion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|string|max:255',
        ]);

        $updated = $this->featureService->updateReleaseVersion($validated['version']);

        return ApiResponse::successOk([
            'message' => "Release version updated for {$updated} user(s)",
            'updated' => $updated,
        ]);
    }
}
