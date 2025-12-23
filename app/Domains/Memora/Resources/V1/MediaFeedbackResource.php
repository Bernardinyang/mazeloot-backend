<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaFeedbackResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'mediaId' => $this->media_uuid,
            'media' => $this->whenLoaded('media', function () {
                return new MediaResource($this->media);
            }, null),
            'type' => $this->type?->value ?? $this->type,
            'content' => $this->content,
            'createdBy' => $this->created_by,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}

