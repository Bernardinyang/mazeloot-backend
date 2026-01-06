<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserFileResource extends JsonResource
{
    public function toArray($request): array
    {
        $fileType = $this->type?->value ?? $this->type;
        $variants = $this->metadata['variants'] ?? null;

        // Filter out full-quality variants (original, large) - only expose preview variants
        $previewVariants = null;
        if ($variants && is_array($variants)) {
            $previewVariants = array_filter($variants, function ($key) {
                return in_array($key, ['thumb', 'medium']);
            }, ARRAY_FILTER_USE_KEY);
            // If empty after filtering, set to null
            if (empty($previewVariants)) {
                $previewVariants = null;
            }
        }

        // For images, use medium variant as the main URL instead of original
        $displayUrl = $this->url;
        if ($fileType === 'image' && $variants && isset($variants['medium'])) {
            $displayUrl = $variants['medium'];
        } elseif ($fileType === 'image' && $variants && isset($variants['thumb'])) {
            $displayUrl = $variants['thumb'];
        }

        return [
            'id' => $this->uuid,
            'url' => $displayUrl,
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
                : ($fileType === 'video'
                    ? ($this->metadata['thumbnail'] ?? ($variants && isset($variants['thumb']) ? $variants['thumb'] : null))
                    : null),
            'variants' => $previewVariants,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
