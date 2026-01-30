<?php

namespace App\Jobs;

use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminsEarlyAccessRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $adminUuids,
        public string $userFirstName,
        public string $userLastName,
        public string $userEmail,
        public string $requestUuid,
        public ?string $reason = null
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        foreach ($this->adminUuids as $adminUuid) {
            $notificationService->create(
                $adminUuid,
                'system',
                'early_access_request',
                'New Early Access Request',
                "{$this->userFirstName} {$this->userLastName} ({$this->userEmail}) has requested early access",
                $this->reason ?? 'No reason provided',
                null,
                "/admin/early-access/requests/{$this->requestUuid}",
                [
                    'request_uuid' => $this->requestUuid,
                    'user_email' => $this->userEmail,
                    'reason' => $this->reason,
                ]
            );
        }
    }
}
