<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Notification\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends web push to all of the notification's user's registered devices.
 * Runs on the "notifications" queue. Monitor failed jobs (e.g. php artisan queue:failed)
 * and ensure a worker is processing the notifications queue for push to be delivered.
 */
class SendWebPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public function __construct(
        public Notification $notification,
        public bool $force = false
    ) {
        $this->onQueue('notifications');
    }

    public function handle(WebPushService $webPushService): void
    {
        $webPushService->sendForNotification($this->notification, $this->force);
    }
}
