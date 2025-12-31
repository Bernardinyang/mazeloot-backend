<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class SelectionResource extends JsonResource
{
    public function toArray($request): array
    {
        // Check if the authenticated user is the owner of this selection
        $isOwner = Auth::check() && Auth::user()->uuid === $this->user_uuid;

        // Get password value (even though it's in $hidden, we can access it via getAttribute)
        $password = $isOwner ? $this->getAttribute('password') : null;

        return [
            'id' => $this->uuid,
            'projectId' => $this->project_uuid,
            'userUuid' => $this->user_uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'color' => $this->color,
            'coverPhotoUrl' => $this->cover_photo_url,
            'coverFocalPoint' => $this->cover_focal_point,
            'hasPassword' => ! empty($this->getAttribute('password')),
            // Only include the actual password if the authenticated user is the owner
            'password' => $password,
            'allowedEmails' => $this->allowed_emails ?? [],
            'selectionCompletedAt' => $this->selection_completed_at?->toIso8601String(),
            'completedByEmail' => $this->completed_by_email,
            'selectionLimit' => $this->selection_limit,
            'resetSelectionLimitAt' => $this->reset_selection_limit_at?->toIso8601String(),
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
