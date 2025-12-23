<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            'hasPassword' => !empty($this->password),
            'selectionCompletedAt' => $this->selection_completed_at?->toIso8601String(),
            'completedByEmail' => $this->completed_by_email,
            'autoDeleteDate' => $this->auto_delete_date?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->media_count ?? 0,
            'selectedCount' => $this->selected_count ?? 0,
            'isStarred' => Auth::check() && $this->relationLoaded('starredByUsers') 
                ? $this->starredByUsers->isNotEmpty() 
                : false,
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }, null),
            'mediaSets' => $this->whenLoaded('mediaSets', function () {
                return MediaSetResource::collection($this->mediaSets);
            }, []),
        ];
    }
}

