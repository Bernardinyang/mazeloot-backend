<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SelectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'projectId' => $this->project_uuid,
            'name' => $this->name,
            'status' => $this->status?->value ?? $this->status,
            'color' => $this->color,
            'coverPhotoUrl' => $this->cover_photo_url,
            'selectionCompletedAt' => $this->selection_completed_at?->toIso8601String(),
            'completedByEmail' => $this->completed_by_email,
            'autoDeleteDate' => $this->auto_delete_date?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->when(isset($this->media_count), $this->media_count),
            'selectedCount' => $this->when(isset($this->selected_count), $this->selected_count),
            'mediaSets' => $this->whenLoaded('mediaSets', function () {
                return MediaSetResource::collection($this->mediaSets);
            }),
        ];
    }
}

