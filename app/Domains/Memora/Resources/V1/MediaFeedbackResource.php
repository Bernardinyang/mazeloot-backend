<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaFeedbackResource extends JsonResource
{
    public function toArray($request): array
    {
        // Ensure created_by is properly decoded (handle both array and JSON string)
        $createdBy = $this->created_by;
        if (is_string($createdBy)) {
            $decoded = json_decode($createdBy, true);
            $createdBy = $decoded !== null ? $decoded : $createdBy;
        }

        // Ensure mentions is properly decoded (handle both array and JSON string)
        $mentions = $this->mentions;
        if (is_string($mentions)) {
            $decoded = json_decode($mentions, true);
            $mentions = $decoded !== null ? $decoded : [];
        }
        if (! is_array($mentions)) {
            $mentions = [];
        }

        return [
            'id' => $this->uuid,
            'mediaId' => $this->media_uuid,
            'parentId' => $this->parent_uuid,
            'timestamp' => $this->timestamp ? (float) $this->timestamp : null,
            'mentions' => ! empty($mentions) ? $mentions : null,
            'media' => $this->whenLoaded('media', function () {
                return new MediaResource($this->media);
            }, null),
            'type' => $this->type?->value ?? $this->type,
            'content' => $this->content,
            'createdBy' => $createdBy,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'replies' => $this->whenLoaded('replies', fn () => MediaFeedbackResource::collection($this->replies), []),
        ];
    }
}
