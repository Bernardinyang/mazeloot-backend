<?php

namespace App\Jobs;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Notifications\ContactFormSubmittedNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class NotifyAdminsContactSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public array $payload,
        public ?string $submissionUuid = null
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notificationService): void
    {
        $adminUuids = Cache::remember('contact_form_admin_uuids', 600, function () {
            return User::whereIn('role', [UserRoleEnum::ADMIN, UserRoleEnum::SUPER_ADMIN])
                ->pluck('uuid')
                ->toArray();
        });

        if (empty($adminUuids)) {
            return;
        }

        $name = trim($this->payload['first_name'].' '.$this->payload['last_name']);
        $title = 'New contact form submission';
        $message = $name.' ('.$this->payload['email'].') sent a message';
        $actionUrl = $this->submissionUuid ? "/admin/contact/{$this->submissionUuid}" : '/admin/contact';

        foreach ($adminUuids as $adminUuid) {
            $notificationService->create(
                $adminUuid,
                'general',
                'contact_form_submission',
                $title,
                $message,
                $this->payload['message'] ?? null,
                null,
                $actionUrl,
                $this->payload
            );

            $admin = User::find($adminUuid);
            if ($admin) {
                $admin->notify(new ContactFormSubmittedNotification($this->payload));
            }
        }
    }
}
