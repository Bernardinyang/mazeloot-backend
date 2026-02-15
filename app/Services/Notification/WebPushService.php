<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public static function shouldPush(Notification $notification): bool
    {
        $type = $notification->type ?? '';

        $silentTypes = [
            'settings_',
            'watermark_updated',
            'preset_updated',
            'social_link_',
            'social_links_reordered',
        ];
        foreach ($silentTypes as $s) {
            if (str_contains($type, $s)) {
                return false;
            }
        }

        $importantTypes = [
            'collection_published',
            'proofing_approved',
            'proofing_rejected',
            'selection_completed',
            'download_ready',
            'media_bulk_uploaded',
            'collection_deleted',
            'selection_deleted',
            'watermark_deleted',
            'preset_deleted',
        ];
        if (in_array($type, $importantTypes)) {
            return true;
        }
        if (str_contains($type, '_created') && ! str_contains($type, 'watermark_created') && ! str_contains($type, 'preset_created')) {
            return true;
        }
        if (str_contains($type, '_uploaded')) {
            return true;
        }

        return false;
    }

    public function sendForNotification(Notification $notification): void
    {
        if (! static::shouldPush($notification)) {
            return;
        }

        $publicKey = config('services.webpush.vapid_public');
        $privateKey = config('services.webpush.vapid_private');
        $subject = config('services.webpush.vapid_subject');
        if (! $publicKey || ! $privateKey || ! $subject) {
            return;
        }

        $subscriptions = PushSubscription::where('user_uuid', $notification->user_uuid)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $notification->title,
            'body' => $notification->message,
            'url' => $notification->action_url ? (str_starts_with($notification->action_url, 'http')
                ? $notification->action_url
                : rtrim(config('app.frontend_url', ''), '/').'/'.ltrim($notification->action_url, '/'))
                : '/',
            'id' => $notification->uuid,
        ]);

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        try {
            $webPush = new WebPush($auth);
            foreach ($subscriptions as $sub) {
                $wpSub = new WebPushSubscription(
                    $sub->endpoint,
                    $sub->public_key,
                    $sub->auth_token,
                    $sub->content_encoding ?? 'aesgcm'
                );
                try {
                    $report = $webPush->sendOneNotification($wpSub, $payload);
                    if ($report->isSubscriptionExpired()) {
                        $sub->delete();
                    }
                } catch (\Throwable $e) {
                    Log::warning('Web push send failed for subscription', [
                        'subscription_id' => $sub->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Web push failed', [
                'notification_uuid' => $notification->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
