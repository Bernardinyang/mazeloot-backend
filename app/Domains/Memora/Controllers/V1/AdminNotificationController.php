<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Services\EmailNotificationService;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminNotificationController extends Controller
{
    protected EmailNotificationService $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get available notification types (admin only)
     */
    public function getTypes(): JsonResponse
    {
        $types = $this->notificationService->getAvailableTypes();

        return ApiResponse::success($types);
    }
}
