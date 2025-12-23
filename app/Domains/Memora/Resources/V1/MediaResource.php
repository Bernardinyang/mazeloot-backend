<?php

namespace App\Domains\Memora\Resources\V1;

use App\Resources\V1\UserFileResource;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'setId' => $this->media_set_uuid,
            'mediaSet' => $this->whenLoaded('mediaSet', function () {
                return new MediaSetResource($this->mediaSet);
            }, null),
            'isSelected' => $this->is_selected,
            'selectedAt' => $this->selected_at?->toIso8601String(),
            'revisionNumber' => $this->revision_number,
            'feedback' => $this->whenLoaded('feedback', function () {
                return MediaFeedbackResource::collection($this->feedback);
            }, []),
            'file' => $this->whenLoaded('file', function () {
                return new UserFileResource($this->file);
            }, null),
            'isCompleted' => $this->is_completed,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'originalMediaId' => $this->original_media_uuid,
            'lowResCopyUrl' => $this->low_res_copy_url,
            'largeImageUrl' => $this->whenLoaded('file', function () {
                $file = $this->file;
                $fileType = $file->type?->value ?? $file->type;
                
                if ($fileType === 'image' && $file->metadata && isset($file->metadata['variants']['large'])) {
                    return $file->metadata['variants']['large'];
                }
                
                return $file->url ?? null;
            }, null),
            'thumbnailUrl' => $this->whenLoaded('file', function () {
                $file = $this->file;
                $fileType = $file->type?->value ?? $file->type;
                
                if ($fileType === 'image' && $file->metadata && isset($file->metadata['variants']['thumb'])) {
                    return $file->metadata['variants']['thumb'];
                }
                
                return $this->thumbnail_url ?? $file->url ?? null;
            }, $this->thumbnail_url),
            'type' => $this->type?->value ?? $this->type,
            'filename' => $this->whenLoaded('file', function () {
                return $this->file->filename ?? $this->filename;
            }, $this->filename),
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

