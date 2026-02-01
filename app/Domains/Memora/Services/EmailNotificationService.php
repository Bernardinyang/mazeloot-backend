<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraEmailNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmailNotificationService
{
    /**
     * Get all email notifications for the authenticated user (type => enabled)
     * Missing types default to config default (true)
     */
    public function getByUser(): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $events = config('email_notifications.events', []);
        $userPrefs = MemoraEmailNotification::where('user_uuid', $user->uuid)
            ->get()
            ->mapWithKeys(fn ($n) => [$n->notification_type => $n->is_enabled])
            ->toArray();

        $result = [];
        foreach ($events as $type => $config) {
            $result[$type] = $userPrefs[$type] ?? ($config['default'] ?? true);
        }

        return $result;
    }

    /**
     * Get all events with user preferences for UI (types with label, description, enabled, group)
     */
    public function getEventsWithPreferences(): array
    {
        $prefs = $this->getByUser();
        $events = config('email_notifications.events', []);
        $groups = config('email_notifications.groups', []);

        $items = [];
        foreach ($events as $type => $config) {
            $items[] = [
                'type' => $type,
                'label' => $config['label'] ?? $type,
                'description' => $config['description'] ?? '',
                'enabled' => $prefs[$type] ?? ($config['default'] ?? true),
                'group' => $config['group'] ?? 'general',
                'groupLabel' => $groups[$config['group'] ?? ''] ?? ucfirst($config['group'] ?? 'general'),
                'critical' => $config['critical'] ?? false,
            ];
        }

        $groupOrder = array_flip(array_keys($groups));

        return collect($items)->sortBy(fn ($i) => ($groupOrder[$i['group']] ?? 99).'_'.$i['type'])->values()->toArray();
    }

    /**
     * Check if user has email notification enabled (used when sending)
     * Default true when no record
     */
    public function isEnabledForUser(string $userUuid, string $type): bool
    {
        $pref = MemoraEmailNotification::where('user_uuid', $userUuid)
            ->where('notification_type', $type)
            ->first();

        if ($pref === null) {
            $events = config('email_notifications.events', []);

            return $events[$type]['default'] ?? true;
        }

        return $pref->is_enabled;
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

        $events = config('email_notifications.events', []);

        DB::beginTransaction();
        try {
            foreach ($notifications as $type => $enabled) {
                if (! isset($events[$type])) {
                    continue;
                }
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
     * Get available notification types from config
     */
    public function getAvailableTypes(): array
    {
        return array_keys(config('email_notifications.events', []));
    }
}
