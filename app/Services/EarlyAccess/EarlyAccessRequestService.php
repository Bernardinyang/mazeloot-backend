<?php

namespace App\Services\EarlyAccess;

use App\Enums\EarlyAccessRequestStatusEnum;
use App\Events\EarlyAccessRequestUpdated;
use App\Jobs\NotifyAdminsEarlyAccessRequest;
use App\Models\EarlyAccessRequest;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EarlyAccessRequestService
{
    public function __construct(
        protected EarlyAccessService $earlyAccessService,
        protected NotificationService $notificationService,
        protected ActivityLogService $activityLogService
    ) {
        // Service dependencies are auto-injected by Laravel
    }

    /**
     * Create an early access request for a user.
     */
    public function createRequest(string $userUuid, ?string $reason = null): EarlyAccessRequest
    {
        $requestRecord = EarlyAccessRequest::create([
            'user_uuid' => $userUuid,
            'reason' => $reason,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        // Queue notification job for admins
        $adminUuids = Cache::remember('early_access_admin_uuids', 600, function () {
            return User::whereIn('role', [\App\Enums\UserRoleEnum::ADMIN, \App\Enums\UserRoleEnum::SUPER_ADMIN])
                ->pluck('uuid')
                ->toArray();
        });

        $user = User::find($userUuid);
        if ($user && !empty($adminUuids)) {
            NotifyAdminsEarlyAccessRequest::dispatch(
                $adminUuids,
                $user->first_name,
                $user->last_name,
                $user->email,
                $requestRecord->uuid,
                $reason
            );
        }

        // Broadcast real-time update
        event(new EarlyAccessRequestUpdated($requestRecord, 'created'));

        return $requestRecord;
    }

    /**
     * Approve an early access request with rewards.
     */
    public function approveRequest(
        EarlyAccessRequest $requestRecord,
        User $admin,
        array $rewards,
        ?Carbon $expiresAt = null,
        ?Request $request = null
    ): \App\Models\EarlyAccessUser {
        return DB::transaction(function () use ($requestRecord, $admin, $rewards, $expiresAt, $request) {
            // Update request status
            $requestRecord->update([
                'status' => EarlyAccessRequestStatusEnum::APPROVED,
                'reviewed_by' => $admin->uuid,
                'reviewed_at' => now(),
            ]);

            // Validate feature flags
            $featureFlags = $rewards['feature_flags'] ?? [];
            $allowedFlags = config('early_access.allowed_features', []);
            $featureFlags = array_intersect($featureFlags, $allowedFlags);
            $rewards['feature_flags'] = $featureFlags;

            // Grant early access with rewards
            $earlyAccess = $this->earlyAccessService->grantEarlyAccess(
                $requestRecord->user_uuid,
                $rewards,
                $expiresAt
            );

            // Notify user
            $this->notificationService->create(
                $requestRecord->user_uuid,
                'system',
                'early_access_approved',
                'Early Access Approved',
                'Your early access request has been approved!',
                'You now have early access to exclusive features and benefits.',
                '/overview',
                [
                    'early_access_uuid' => $earlyAccess->uuid,
                ]
            );

            // Log activity
            $this->activityLogService->logQueued(
                action: 'early_access_request_approved',
                subject: $requestRecord,
                description: "Early access request approved for {$requestRecord->user->email}",
                properties: [
                    'request_uuid' => $requestRecord->uuid,
                    'user_uuid' => $requestRecord->user_uuid,
                    'user_email' => $requestRecord->user->email,
                    'early_access_uuid' => $earlyAccess->uuid,
                    'rewards' => $rewards,
                ],
                causer: $admin,
                request: $request
            );

            // Broadcast real-time update
            event(new EarlyAccessRequestUpdated($requestRecord, 'approved'));

            return $earlyAccess;
        });
    }

    /**
     * Reject an early access request.
     */
    public function rejectRequest(
        EarlyAccessRequest $requestRecord,
        User $admin,
        ?string $rejectionReason = null,
        ?Request $request = null
    ): void {
        DB::transaction(function () use ($requestRecord, $admin, $rejectionReason, $request) {
            // Update request status
            $requestRecord->update([
                'status' => EarlyAccessRequestStatusEnum::REJECTED,
                'reviewed_by' => $admin->uuid,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            // Notify user
            $this->notificationService->create(
                $requestRecord->user_uuid,
                'system',
                'early_access_rejected',
                'Early Access Request Rejected',
                'Your early access request has been rejected.',
                $rejectionReason ?? 'We appreciate your interest. Please try again later.',
                '/early-access',
                []
            );

            // Log activity
            $this->activityLogService->logQueued(
                action: 'early_access_request_rejected',
                subject: $requestRecord,
                description: "Early access request rejected for {$requestRecord->user->email}",
                properties: [
                    'request_uuid' => $requestRecord->uuid,
                    'user_uuid' => $requestRecord->user_uuid,
                    'user_email' => $requestRecord->user->email,
                    'rejection_reason' => $rejectionReason,
                ],
                causer: $admin,
                request: $request
            );

            // Broadcast real-time update
            event(new EarlyAccessRequestUpdated($requestRecord, 'rejected'));
        });
    }
}
