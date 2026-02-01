<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraEmailNotification;
use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmailNotificationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $systemUser = User::where('email', 'superadmin@example.com')->first()
            ?? User::where('role', UserRoleEnum::SUPER_ADMIN)->first();

        if (! $systemUser) {
            $this->command->error('No admin user found. Please run DatabaseSeeder first.');

            return;
        }

        $events = config('email_notifications.events', []);

        foreach ($events as $type => $config) {
            MemoraEmailNotification::updateOrCreate(
                [
                    'user_uuid' => $systemUser->uuid,
                    'notification_type' => $type,
                ],
                [
                    'is_enabled' => $config['default'] ?? true,
                ]
            );
        }

        $this->command->info('Email notification types seeded from config.');
    }
}
