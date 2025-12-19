<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'setId' => $this->media_set_uuid,
            'isSelected' => $this->is_selected,
            'selectedAt' => $this->selected_at?->toIso8601String(),
            'revisionNumber' => $this->revision_number,
            'feedback' => $this->whenLoaded('feedback', function () {
                return $this->feedback->map(fn($f) => [
                    'id' => $f->uuid ?? $f->id,
                    'type' => $f->type,
                    'content' => $f->content,
                    'createdAt' => $f->created_at->toIso8601String(),
                    'createdBy' => $f->created_by ?? null,
                ]);
            }, []),
            'isCompleted' => $this->is_completed,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'originalMediaId' => $this->original_media_uuid,
            'lowResCopyUrl' => $this->low_res_copy_url,
            'url' => $this->url,
            'thumbnailUrl' => $this->thumbnail_url,
            'type' => $this->type?->value ?? $this->type,
            'filename' => $this->filename,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'order' => $this->order,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}

