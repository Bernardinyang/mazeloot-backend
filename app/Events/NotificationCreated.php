<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Notification $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification->load('user');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->notification->user_uuid),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->uuid,
                'product' => $this->notification->product,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'message' => $this->notification->message,
                'description' => $this->notification->description,
                'actionUrl' => $this->notification->action_url,
                'metadata' => $this->notification->metadata,
                'readAt' => $this->notification->read_at?->toIso8601String(),
                'createdAt' => $this->notification->created_at->toIso8601String(),
                'updatedAt' => $this->notification->updated_at->toIso8601String(),
            ],
        ];
    }
}
