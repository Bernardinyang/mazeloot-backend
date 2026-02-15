<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebPushJob;
use App\Models\Notification;
use App\Models\User;
use App\Resources\V1\NotificationResource;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Notification\NotificationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * List notifications for a user (admin). Enables finding a notification to resend push.
     */
    public function indexForUser(Request $request, string $userUuid): JsonResponse
    {
        $user = User::find($userUuid);
        if (! $user) {
            return ApiResponse::errorNotFound('User not found');
        }

        $this->activityLogService->log(
            'admin_viewed_user_notifications',
            null,
            'Admin viewed user notifications',
            ['target_user_uuid' => $userUuid, 'target_user_email' => $user->email],
            $request->user(),
            $request
        );

        $product = $request->query('product');
        $unread = $request->has('unread') ? filter_var($request->query('unread'), FILTER_VALIDATE_BOOLEAN) : null;
        $limit = min((int) $request->query('limit', 50), 100);

        $notifications = $this->notificationService->getForUserUuid($userUuid, $product, $unread, $limit);

        return ApiResponse::successOk(NotificationResource::collection($notifications));
    }

    /**
     * Re-dispatch web push for a notification (superadmin only).
     * Use when push may have failed or not been delivered.
     */
    public function resendPush(Request $request, string $uuid): JsonResponse
    {
        $notification = Notification::find($uuid);

        if (! $notification) {
            return ApiResponse::errorNotFound('Notification not found');
        }

        SendWebPushJob::dispatch($notification);

        $this->activityLogService->log(
            'admin_notification_push_resent',
            $notification,
            'Admin re-dispatched web push for notification',
            [
                'notification_uuid' => $notification->uuid,
                'target_user_uuid' => $notification->user_uuid,
            ],
            $request->user(),
            $request
        );

        return ApiResponse::successOk([
            'message' => 'Web push re-dispatched',
            'notification_uuid' => $notification->uuid,
        ]);
    }
}
