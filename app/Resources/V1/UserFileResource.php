<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserFileResource extends JsonResource
{
    public function toArray($request): array
    {
        $fileType = $this->type?->value ?? $this->type;
        $variants = $this->metadata['variants'] ?? null;
        
        return [
            'id' => $this->uuid,
            'url' => $this->url,
            'path' => $this->path,
            'type' => $fileType,
            'filename' => $this->filename,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'metadata' => $this->metadata,
            'thumbnailUrl' => $fileType === 'image' && $variants && isset($variants['thumb'])
                ? $variants['thumb']
                : ($fileType === 'video' ? $this->url : null),
            'variants' => $variants,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}

