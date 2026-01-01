<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraEmailNotification;
use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmailNotificationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a system user for seeding notification types
        // This makes the types available system-wide via getAvailableTypes()
        $systemUser = User::where('email', 'superadmin@example.com')->first();

        if (!$systemUser) {
            $this->command->warn('System user not found. Creating default notification types for first admin user.');
            $systemUser = User::where('role', UserRoleEnum::SUPER_ADMIN)->first();

            if (!$systemUser) {
                $this->command->error('No admin user found. Please run DatabaseSeeder first.');
                return;
            }
        }

        $notificationTypes = [
            // Collection Phase Notifications
            [
                'type' => 'collection_published',
                'enabled' => true,
                'description' => 'Receive an email when a collection is published',
            ],
            [
                'type' => 'collection_shared',
                'enabled' => true,
                'description' => 'Receive an email when a collection is shared',
            ],
            [
                'type' => 'collection_download',
                'enabled' => true,
                'description' => 'Receive an email when clients download from collections',
            ],
            [
                'type' => 'collection_email_registration',
                'enabled' => true,
                'description' => 'Receive an email when clients register their email to access collections',
            ],
            [
                'type' => 'collection_favorite',
                'enabled' => false,
                'description' => 'Receive an email when clients favorite photos in collections',
            ],
            [
                'type' => 'collection_view',
                'enabled' => false,
                'description' => 'Receive an email when collections are viewed',
            ],

            // Selection Phase Notifications
            [
                'type' => 'selection_published',
                'enabled' => true,
                'description' => 'Receive an email when a selection is published',
            ],
            [
                'type' => 'selection_completed',
                'enabled' => true,
                'description' => 'Receive an email when a selection is completed',
            ],
            [
                'type' => 'selection_access',
                'enabled' => true,
                'description' => 'Receive an email when clients access selections',
            ],
            [
                'type' => 'selection_limit_reached',
                'enabled' => true,
                'description' => 'Receive an email when selection limit is reached',
            ],

            // Proofing Phase Notifications
            [
                'type' => 'proofing_published',
                'enabled' => true,
                'description' => 'Receive an email when proofing is published',
            ],
            [
                'type' => 'proofing_completed',
                'enabled' => true,
                'description' => 'Receive an email when proofing is completed',
            ],
            [
                'type' => 'proofing_comment',
                'enabled' => true,
                'description' => 'Receive an email when comments are added to proofing',
            ],
            [
                'type' => 'proofing_revision_uploaded',
                'enabled' => true,
                'description' => 'Receive an email when new revisions are uploaded',
            ],
            [
                'type' => 'proofing_approval_requested',
                'enabled' => true,
                'description' => 'Receive an email when approval requests are submitted',
            ],
            [
                'type' => 'proofing_approval_approved',
                'enabled' => true,
                'description' => 'Receive an email when approval requests are approved',
            ],
            [
                'type' => 'proofing_approval_rejected',
                'enabled' => true,
                'description' => 'Receive an email when approval requests are rejected',
            ],
            [
                'type' => 'proofing_closure_requested',
                'enabled' => true,
                'description' => 'Receive an email when closure requests are submitted',
            ],
            [
                'type' => 'proofing_closure_approved',
                'enabled' => true,
                'description' => 'Receive an email when closure requests are approved',
            ],
            [
                'type' => 'proofing_closure_rejected',
                'enabled' => true,
                'description' => 'Receive an email when closure requests are rejected',
            ],

            // Project Notifications
            [
                'type' => 'project_created',
                'enabled' => false,
                'description' => 'Receive an email when a new project is created',
            ],
            [
                'type' => 'project_updated',
                'enabled' => false,
                'description' => 'Receive an email when a project is updated',
            ],

            // Media & Feedback Notifications
            [
                'type' => 'media_feedback',
                'enabled' => true,
                'description' => 'Receive an email when clients provide feedback on media',
            ],
            [
                'type' => 'media_uploaded',
                'enabled' => false,
                'description' => 'Receive an email when media is uploaded',
            ],

            // General Notifications
            [
                'type' => 'weekly_summary',
                'enabled' => false,
                'description' => 'Receive a weekly summary of your activity',
            ],
            [
                'type' => 'monthly_summary',
                'enabled' => false,
                'description' => 'Receive a monthly summary of your activity',
            ],
        ];

        foreach ($notificationTypes as $notification) {
            MemoraEmailNotification::updateOrCreate(
                [
                    'user_uuid' => $systemUser->uuid,
                    'notification_type' => $notification['type'],
                ],
                [
                    'is_enabled' => $notification['enabled'],
                ]
            );
        }

        $this->command->info('Default email notification types seeded successfully.');
    }
}

