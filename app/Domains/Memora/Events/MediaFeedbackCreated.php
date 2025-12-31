<?php

namespace App\Domains\Memora\Events;

use App\Domains\Memora\Models\MemoraMediaFeedback;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaFeedbackCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MemoraMediaFeedback $feedback;

    /**
     * Create a new event instance.
     */
    public function __construct(MemoraMediaFeedback $feedback)
    {
        $this->feedback = $feedback->load(['replies', 'parent']);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('media.'.$this->feedback->media_uuid.'.feedback'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'feedback.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'feedback' => [
                'id' => $this->feedback->uuid,
                'mediaId' => $this->feedback->media_uuid,
                'parentId' => $this->feedback->parent_uuid,
                'timestamp' => $this->feedback->timestamp ? (float) $this->feedback->timestamp : null,
                'type' => $this->feedback->type?->value ?? $this->feedback->type,
                'content' => $this->feedback->content,
                'createdBy' => $this->feedback->created_by,
                'createdAt' => $this->feedback->created_at->toIso8601String(),
                'updatedAt' => $this->feedback->updated_at->toIso8601String(),
            ],
        ];
    }
}
