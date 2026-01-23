<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $metadata = $this->metadata ?? [];
        
        return [
            'id' => $this->uuid,
            'product' => $this->product,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'description' => $this->description,
            'actionUrl' => $this->action_url,
            'priority' => $metadata['priority'] ?? 'LOW',
            'metadata' => $metadata,
            'readAt' => $this->read_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
