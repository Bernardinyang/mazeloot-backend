<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Requests\V1\UpdateNotificationChannelPreferencesRequest;
use App\Domains\Memora\Requests\V1\UpdateNotificationSettingsRequest;
use App\Domains\Memora\Services\EmailNotificationService;
use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationChannelPreferenceService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class EmailNotificationController extends Controller
{
    protected EmailNotificationService $notificationService;

    protected NotificationChannelPreferenceService $channelPreferenceService;

    public function __construct(
        EmailNotificationService $notificationService,
        NotificationChannelPreferenceService $channelPreferenceService
    ) {
        $this->notificationService = $notificationService;
        $this->channelPreferenceService = $channelPreferenceService;
    }

    /**
     * Get user's email notification settings (legacy: type => enabled)
     */
    public function index(): JsonResponse
    {
        $notifications = $this->notificationService->getByUser();

        return ApiResponse::success($notifications);
    }

    /**
     * Get all events with user preferences (for UI)
     */
    public function events(): JsonResponse
    {
        $events = $this->notificationService->getEventsWithPreferences();

        return ApiResponse::success($events);
    }

    /**
     * Bulk update email notifications
     */
    public function update(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $notifications = $this->notificationService->bulkUpdate($request->validated()['notifications']);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'notification_settings_updated',
                null,
                'Email notification settings updated',
                null,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log notification settings activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($notifications);
    }

    /**
     * Get notification delivery channel preferences (email, in-app, whatsapp)
     */
    public function channels(): JsonResponse
    {
        $prefs = $this->channelPreferenceService->getForUser('memora');

        return ApiResponse::success($prefs);
    }

    /**
     * Update notification delivery channel preferences
     */
    public function updateChannels(UpdateNotificationChannelPreferencesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (array_key_exists('notify_whatsapp', $validated) && ! $validated['notify_whatsapp']) {
            $validated['whatsapp_number'] = null;
        }
        $prefs = $this->channelPreferenceService->update('memora', $validated);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'notification_channels_updated',
                null,
                'Notification channel preferences updated',
                null,
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log notification channels activity', ['error' => $e->getMessage()]);
        }

        return ApiResponse::success($prefs);
    }
}
