<?php

namespace App\Jobs;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Notifications\NewsletterSubscribedNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class NotifyAdminsNewsletter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public string $email,
        public string $newsletterUuid
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notificationService): void
    {
        $adminUuids = Cache::remember('newsletter_admin_uuids', 600, function () {
            return User::whereIn('role', [UserRoleEnum::ADMIN, UserRoleEnum::SUPER_ADMIN])
                ->pluck('uuid')
                ->toArray();
        });

        if (empty($adminUuids)) {
            return;
        }

        $title = 'New newsletter subscription';
        $message = "{$this->email} subscribed to the newsletter";
        $actionUrl = '/admin/newsletter';

        foreach ($adminUuids as $adminUuid) {
            $notificationService->create(
                $adminUuid,
                'general',
                'newsletter_subscription',
                $title,
                $message,
                null,
                null,
                $actionUrl,
                [
                    'newsletter_uuid' => $this->newsletterUuid,
                    'email' => $this->email,
                ]
            );

            $admin = User::find($adminUuid);
            if ($admin) {
                $admin->notify(new NewsletterSubscribedNotification(
                    $this->email,
                    $this->newsletterUuid
                ));
            }
        }
    }
}
