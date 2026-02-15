<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Resources\V1\NotificationResource;
use App\Services\Notification\NotificationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get notifications for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $product = $request->query('product');
        $unread = $request->has('unread') ? filter_var($request->query('unread'), FILTER_VALIDATE_BOOLEAN) : null;
        $limit = $request->has('limit') ? (int) $request->query('limit') : null;

        $notifications = $this->notificationService->getForUser($product, $unread, $limit);

        return ApiResponse::success(NotificationResource::collection($notifications));
    }

    /**
     * Get unread counts by product
     */
    public function unreadCount(): JsonResponse
    {
        $counts = $this->notificationService->getUnreadCounts();

        return ApiResponse::success($counts);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $this->notificationService->markAsRead($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'notification_marked_read',
                null,
                'Notification marked as read',
                ['notification_id' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log notification activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(null, 204);
    }

    /**
     * Mark all notifications as read (optionally filtered by product)
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $product = $request->query('product');
        $count = $this->notificationService->markAllAsRead($product);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'notifications_marked_all_read',
                null,
                'All notifications marked as read',
                ['count' => $count, 'product' => $product],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log notification activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(['count' => $count]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->notificationService->delete($id);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'notification_deleted',
                null,
                'Notification deleted',
                ['notification_id' => $id],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log notification activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success(null, 204);
    }

    /**
     * Get VAPID public key for push subscription (client uses this to subscribe).
     */
    public function pushVapidPublic(): JsonResponse
    {
        $key = config('services.webpush.vapid_public');
        if (! $key) {
            return ApiResponse::error('Web push is not configured', 503);
        }

        return ApiResponse::success(['publicKey' => $key]);
    }

    /**
     * Store push subscription for the authenticated user.
     */
    public function storePushSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $user = $request->user();
        $encoding = $validated['contentEncoding'] ?? 'aesgcm';

        PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_uuid' => $user->uuid,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $encoding,
            ]
        );

        return ApiResponse::success(null, 204);
    }

    /**
     * Delete push subscription by endpoint.
     */
    public function destroyPushSubscription(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->pushSubscriptions()->where('endpoint', $request->input('endpoint'))->delete();

        return ApiResponse::success(null, 204);
    }
}
