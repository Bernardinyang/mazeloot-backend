<?php

namespace App\Services\Notification;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Determine notification priority based on type.
     *
     * @param  string  $type  Notification type
     * @return string Priority: HIGH, MEDIUM, or LOW
     */
    private function determinePriority(string $type): string
    {
        // HIGH: Critical actions requiring immediate attention
        if (
            str_contains($type, 'rejected') ||
            str_contains($type, 'approval_requested') ||
            str_contains($type, 'closure_requested') ||
            str_contains($type, 'limit_reached') ||
            str_contains($type, 'failed') ||
            str_contains($type, 'error') ||
            str_contains($type, 'deleted')
        ) {
            return 'HIGH';
        }

        // MEDIUM: Important workflow milestones
        if (
            str_contains($type, 'completed') ||
            str_contains($type, 'published') ||
            str_contains($type, 'approved') ||
            str_contains($type, 'comment') ||
            str_contains($type, 'revision_uploaded') ||
            str_contains($type, 'feedback') ||
            str_contains($type, 'download') ||
            str_contains($type, 'access') ||
            str_contains($type, 'shared') ||
            str_contains($type, 'uploaded')
        ) {
            return 'MEDIUM';
        }

        // LOW: Informational updates
        return 'LOW';
    }

    /**
     * Create a notification for a user.
     *
     * @param  string  $userUuid  User UUID
     * @param  string  $product  Product name (memora, profolio, general)
     * @param  string  $type  Notification type (e.g., 'collection_created')
     * @param  string  $title  Notification title
     * @param  string  $message  Notification message
     * @param  string|null  $description  Optional short description
     * @param  string|null  $detail  Optional long-form explanation for the user
     * @param  string|null  $actionUrl  Optional action URL
     * @param  array|null  $metadata  Optional metadata
     */
    public function create(
        string $userUuid,
        string $product,
        string $type,
        string $title,
        string $message,
        ?string $description = null,
        ?string $detail = null,
        ?string $actionUrl = null,
        ?array $metadata = null
    ): Notification {
        // Determine priority and merge into metadata
        // Allow metadata to override priority if explicitly set, otherwise determine from type
        $priority = $metadata['priority'] ?? $this->determinePriority($type);

        // Ensure priority is valid
        if (! in_array($priority, ['HIGH', 'MEDIUM', 'LOW'])) {
            $priority = $this->determinePriority($type);
        }

        $finalMetadata = array_merge(
            $metadata ?? [],
            ['priority' => $priority]
        );

        $notification = Notification::create([
            'user_uuid' => $userUuid,
            'product' => $product,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'description' => $description,
            'detail' => $detail,
            'action_url' => $actionUrl,
            'metadata' => $finalMetadata,
        ]);

        // Broadcast the notification
        event(new NotificationCreated($notification));

        // Log activity for notification creation (admin visibility)
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                'notification_created',
                $notification,
                'Notification created',
                [
                    'user_uuid' => $notification->user_uuid,
                    'product' => $notification->product,
                    'type' => $notification->type,
                    'title' => $notification->title,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to log notification activity', [
                'notification_uuid' => $notification->uuid ?? null,
                'user_uuid' => $notification->user_uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    /**
     * Get notifications for the authenticated user.
     *
     * @param  string|null  $product  Filter by product
     * @param  bool|null  $unread  Filter by read status (true = unread only, false = read only, null = all)
     * @param  int|null  $limit  Limit results
     */
    public function getForUser(?string $product = null, ?bool $unread = null, ?int $limit = null): Collection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = Notification::forUser($user->uuid)
            ->orderBy('created_at', 'desc');

        if ($product) {
            $query->forProduct($product);
        }

        if ($unread === true) {
            $query->unread();
        } elseif ($unread === false) {
            $query->read();
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get unread count by product for the authenticated user.
     */
    public function getUnreadCounts(): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $notifications = Notification::forUser($user->uuid)
            ->unread()
            ->selectRaw('product, COUNT(*) as count')
            ->groupBy('product')
            ->pluck('count', 'product')
            ->toArray();

        return [
            'memora' => (int) ($notifications['memora'] ?? 0),
            'profolio' => (int) ($notifications['profolio'] ?? 0),
            'general' => (int) ($notifications['general'] ?? 0),
            'total' => array_sum($notifications),
        ];
    }

    /**
     * Mark a notification as read.
     *
     * @param  string  $notificationUuid  Notification UUID
     */
    public function markAsRead(string $notificationUuid): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $notification = Notification::forUser($user->uuid)
            ->findOrFail($notificationUuid);

        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for the authenticated user.
     *
     * @param  string|null  $product  Filter by product
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(?string $product = null): int
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $query = Notification::forUser($user->uuid)->unread();

        if ($product) {
            $query->forProduct($product);
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * Delete a notification.
     *
     * @param  string  $notificationUuid  Notification UUID
     */
    public function delete(string $notificationUuid): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $notification = Notification::forUser($user->uuid)
            ->findOrFail($notificationUuid);

        return $notification->delete();
    }
}
