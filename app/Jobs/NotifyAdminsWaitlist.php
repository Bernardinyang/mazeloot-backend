<?php

namespace App\Jobs;

use App\Enums\UserRoleEnum;
use App\Models\Product;
use App\Models\User;
use App\Notifications\WaitlistAddedNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class NotifyAdminsWaitlist implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public string $name,
        public string $email,
        public string $waitlistUuid,
        public ?string $productUuid = null
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notificationService): void
    {
        $adminUuids = Cache::remember('waitlist_admin_uuids', 600, function () {
            return User::whereIn('role', [UserRoleEnum::ADMIN, UserRoleEnum::SUPER_ADMIN])
                ->pluck('uuid')
                ->toArray();
        });

        if (empty($adminUuids)) {
            return;
        }

        $productName = 'Memora';
        if ($this->productUuid) {
            $product = Product::where('uuid', $this->productUuid)->first();
            if ($product) {
                $productName = $product->name;
            }
        }

        $title = 'New waitlist signup';
        $message = "{$this->name} ({$this->email}) joined the waitlist for {$productName}";
        $actionUrl = "/admin/waitlist/{$this->waitlistUuid}";

        foreach ($adminUuids as $adminUuid) {
            $notificationService->create(
                $adminUuid,
                'general',
                'waitlist_signup',
                $title,
                $message,
                null,
                null,
                $actionUrl,
                [
                    'waitlist_uuid' => $this->waitlistUuid,
                    'name' => $this->name,
                    'email' => $this->email,
                    'product_uuid' => $this->productUuid,
                    'product_name' => $productName,
                ]
            );

            $admin = User::find($adminUuid);
            if ($admin) {
                $admin->notify(new WaitlistAddedNotification(
                    $this->name,
                    $this->email,
                    $productName,
                    $this->waitlistUuid
                ));
            }
        }
    }
}
