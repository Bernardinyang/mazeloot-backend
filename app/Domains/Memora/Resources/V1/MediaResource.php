<?php

namespace App\Domains\Memora\Resources\V1;

use App\Resources\V1\UserFileResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            'isRejected' => $this->is_rejected ?? false,
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'isPrivate' => $this->is_private ?? false,
            'markedPrivateAt' => $this->marked_private_at?->toIso8601String(),
            'markedPrivateByEmail' => $this->marked_private_by_email,
            'isReadyForRevision' => $this->is_ready_for_revision,
            'isRevised' => $this->is_revised ?? false,
            'revisionTodos' => $this->revision_todos ?? [],
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

                // For videos, check metadata for thumbnail
                if ($fileType === 'video' && $file->metadata) {
                    if (isset($file->metadata['thumbnail'])) {
                        return $file->metadata['thumbnail'];
                    }
                    if (isset($file->metadata['variants']['thumb'])) {
                        return $file->metadata['variants']['thumb'];
                    }
                }

                return $this->thumbnail_url ?? $file->url ?? null;
            }, $this->thumbnail_url),
            'type' => $this->whenLoaded('file', function () {
                return $this->file->type?->value ?? $this->file->type;
            }, null),
            'filename' => $this->whenLoaded('file', function () {
                return $this->file->filename;
            }, null),
            'mimeType' => $this->whenLoaded('file', function () {
                return $this->file->mime_type;
            }, null),
            'size' => $this->whenLoaded('file', function () {
                return $this->file->size;
            }, null),
            'width' => $this->whenLoaded('file', function () {
                return $this->file->width;
            }, null),
            'height' => $this->whenLoaded('file', function () {
                return $this->file->height;
            }, null),
            'order' => $this->order,
            'watermarkUuid' => $this->watermark_uuid,
            'isStarred' => $this->getAttribute('isCollectionFavourited') ?? (Auth::check() && $this->relationLoaded('starredByUsers')
                ? $this->starredByUsers->isNotEmpty()
                : false),
            'isFeatured' => $this->is_featured ?? false,
            'is_featured' => $this->is_featured ?? false,
            'featuredAt' => $this->featured_at?->toIso8601String(),
            'featured_at' => $this->featured_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
