<?php

namespace App\Jobs;

use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminsUpgradeRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public array $adminUuids,
        public string $userFirstName,
        public string $userLastName,
        public string $userEmail,
        public string $requestUuid,
        public ?string $currentTier,
        public string $targetTier
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notificationService): void
    {
        $from = $this->currentTier ?? 'Starter';
        foreach ($this->adminUuids as $adminUuid) {
            $notificationService->create(
                $adminUuid,
                'general',
                'upgrade_request',
                'New upgrade request',
                "{$this->userFirstName} {$this->userLastName} ({$this->userEmail}) has requested to upgrade from {$from} to {$this->targetTier}",
                null,
                null,
                "/admin/upgrade-requests/{$this->requestUuid}",
                [
                    'request_uuid' => $this->requestUuid,
                    'user_email' => $this->userEmail,
                    'current_tier' => $this->currentTier,
                    'target_tier' => $this->targetTier,
                ]
            );
        }
    }
}
