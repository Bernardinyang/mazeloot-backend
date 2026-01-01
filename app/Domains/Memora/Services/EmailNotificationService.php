<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraEmailNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmailNotificationService
{
    /**
     * Get all email notifications for the authenticated user
     */
    public function getByUser(): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraEmailNotification::where('user_uuid', $user->uuid)
            ->get()
            ->mapWithKeys(function ($notification) {
                return [$notification->notification_type => $notification->is_enabled];
            })
            ->toArray();
    }

    /**
     * Update a single notification
     */
    public function updateNotification(string $type, bool $enabled): MemoraEmailNotification
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraEmailNotification::updateOrCreate(
            [
                'user_uuid' => $user->uuid,
                'notification_type' => $type,
            ],
            [
                'is_enabled' => $enabled,
            ]
        );
    }

    /**
     * Bulk update notifications
     */
    public function bulkUpdate(array $notifications): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        DB::beginTransaction();
        try {
            foreach ($notifications as $type => $enabled) {
                MemoraEmailNotification::updateOrCreate(
                    [
                        'user_uuid' => $user->uuid,
                        'notification_type' => $type,
                    ],
                    [
                        'is_enabled' => (bool) $enabled,
                    ]
                );
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->getByUser();
    }

    /**
     * Get available notification types (for admin)
     */
    public function getAvailableTypes(): array
    {
        return MemoraEmailNotification::distinct()
            ->pluck('notification_type')
            ->sort()
            ->values()
            ->toArray();
    }
}
