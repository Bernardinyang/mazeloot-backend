<?php

namespace App\Events;

use App\Models\EarlyAccessRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EarlyAccessRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public EarlyAccessRequest $request;

    public string $action;

    /**
     * Create a new event instance.
     */
    public function __construct(EarlyAccessRequest $request, string $action = 'updated')
    {
        $this->request = $request->load(['user', 'reviewer']);
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.early-access'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'early-access-request.'.$this->action;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'request' => [
                'uuid' => $this->request->uuid,
                'user_uuid' => $this->request->user_uuid,
                'user' => [
                    'uuid' => $this->request->user->uuid,
                    'first_name' => $this->request->user->first_name,
                    'last_name' => $this->request->user->last_name,
                    'email' => $this->request->user->email,
                ],
                'status' => $this->request->status->value,
                'reason' => $this->request->reason,
                'rejection_reason' => $this->request->rejection_reason,
                'reviewed_by' => $this->request->reviewed_by,
                'reviewed_at' => $this->request->reviewed_at?->toIso8601String(),
                'created_at' => $this->request->created_at->toIso8601String(),
                'updated_at' => $this->request->updated_at->toIso8601String(),
            ],
            'action' => $this->action,
        ];
    }
}
