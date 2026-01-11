<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
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
    public function markAsRead(string $id): JsonResponse
    {
        $this->notificationService->markAsRead($id);

        return ApiResponse::success(null, 204);
    }

    /**
     * Mark all notifications as read (optionally filtered by product)
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $product = $request->query('product');
        $count = $this->notificationService->markAllAsRead($product);

        return ApiResponse::success(['count' => $count]);
    }

    /**
     * Delete a notification
     */
    public function destroy(string $id): JsonResponse
    {
        $this->notificationService->delete($id);

        return ApiResponse::success(null, 204);
    }
}
