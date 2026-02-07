<?php

namespace App\Jobs;

use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminsDowngradeRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public array $adminUuids,
        public string $userFirstName,
        public string $userLastName,
        public string $userEmail,
        public string $requestUuid,
        public string $currentTier
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notificationService): void
    {
        foreach ($this->adminUuids as $adminUuid) {
            $notificationService->create(
                $adminUuid,
                'general',
                'downgrade_request',
                'New downgrade request',
                "{$this->userFirstName} {$this->userLastName} ({$this->userEmail}) has requested to downgrade from {$this->currentTier}",
                null,
                null,
                "/admin/downgrade-requests/{$this->requestUuid}",
                [
                    'request_uuid' => $this->requestUuid,
                    'user_email' => $this->userEmail,
                    'current_tier' => $this->currentTier,
                ]
            );
        }
    }
}
