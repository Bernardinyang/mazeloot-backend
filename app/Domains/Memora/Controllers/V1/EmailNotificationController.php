<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\UpdateNotificationSettingsRequest;
use App\Domains\Memora\Services\EmailNotificationService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class EmailNotificationController extends Controller
{
    protected EmailNotificationService $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user's email notification settings
     */
    public function index(): JsonResponse
    {
        $notifications = $this->notificationService->getByUser();
        return ApiResponse::success($notifications);
    }

    /**
     * Bulk update email notifications
     */
    public function update(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $notifications = $this->notificationService->bulkUpdate($request->validated()['notifications']);
        return ApiResponse::success($notifications);
    }
}
