<?php

namespace App\Services\Notification;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    /**
     * Create a notification for a user.
     *
     * @param  string  $userUuid  User UUID
     * @param  string  $product  Product name (memora, profolio, general)
     * @param  string  $type  Notification type (e.g., 'collection_created')
     * @param  string  $title  Notification title
     * @param  string  $message  Notification message
     * @param  string|null  $description  Optional description
     * @param  string|null  $actionUrl  Optional action URL
     * @param  array|null  $metadata  Optional metadata
     * @return Notification
     */
    public function create(
        string $userUuid,
        string $product,
        string $type,
        string $title,
        string $message,
        ?string $description = null,
        ?string $actionUrl = null,
        ?array $metadata = null
    ): Notification {
        $notification = Notification::create([
            'user_uuid' => $userUuid,
            'product' => $product,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'description' => $description,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);

        // Broadcast the notification
        event(new NotificationCreated($notification));

        return $notification;
    }

    /**
     * Get notifications for the authenticated user.
     *
     * @param  string|null  $product  Filter by product
     * @param  bool|null  $unread  Filter by read status (true = unread only, false = read only, null = all)
     * @param  int|null  $limit  Limit results
     * @return Collection
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
     *
     * @return array
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
     * @return bool
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
     * @return int  Number of notifications marked as read
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
     * @return bool
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
