<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserFileResource extends JsonResource
{
    public function toArray($request): array
    {
        $fileType = $this->type?->value ?? $this->type;
        $variants = $this->metadata['variants'] ?? null;

        // Check if this is a public selection request (has guest token with selection_uuid)
        $isPublicSelection = false;
        $guestToken = $request->attributes->get('guest_token');
        if ($guestToken) {
            // Check if guest token has selection_uuid property (could be GuestSelectionToken model)
            $selectionUuid = null;
            if (is_object($guestToken)) {
                $selectionUuid = $guestToken->selection_uuid ?? ($guestToken->getAttribute('selection_uuid') ?? null);
            } elseif (is_array($guestToken)) {
                $selectionUuid = $guestToken['selection_uuid'] ?? null;
            }
            if ($selectionUuid) {
                $isPublicSelection = true;
            }
        }
        
        // Check if media has a watermark - if so, ignore preview variant
        $hasMediaWatermark = ! empty($request->attributes->get('media_watermark_uuid'));

        // Filter out full-quality variants (original, large) - only expose preview variants
        // Include 'preview' variant for public selection requests, but only if media doesn't have a watermark
        $previewVariants = null;
        if ($variants && is_array($variants)) {
            $allowedVariants = ['thumb', 'medium'];
            if ($isPublicSelection && ! $hasMediaWatermark) {
                $allowedVariants[] = 'preview';
            }
            
            $previewVariants = array_filter($variants, function ($key) use ($allowedVariants) {
                return in_array($key, $allowedVariants);
            }, ARRAY_FILTER_USE_KEY);
            // If empty after filtering, set to null
            if (empty($previewVariants)) {
                $previewVariants = null;
            }
        }
        
        // Determine display URL
        $displayUrl = $this->url;
        
        if ($fileType === 'image') {
            // For public selection, use preview variant if available and no watermark exists
            if ($isPublicSelection && ! $hasMediaWatermark && $variants && isset($variants['preview'])) {
                $displayUrl = $variants['preview'];
            } elseif ($variants && isset($variants['medium'])) {
                // Use medium variant as the main URL instead of original
                $displayUrl = $variants['medium'];
            } elseif ($variants && isset($variants['thumb'])) {
                $displayUrl = $variants['thumb'];
            }
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
