<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProofingResource extends JsonResource
{
    public function toArray($request): array
    {
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
            'allowedEmails' => $this->allowed_emails ?? [],
            'primaryEmail' => $this->primary_email,
            'maxRevisions' => $this->max_revisions,
            'currentRevision' => $this->current_revision,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->when(isset($this->media_count), $this->media_count),
            'completedCount' => $this->when(isset($this->completed_count), $this->completed_count),
            'pendingCount' => $this->when(isset($this->pending_count), $this->pending_count),
            'setCount' => $this->when(isset($this->set_count), $this->set_count) ?? ($this->relationLoaded('mediaSets') ? $this->mediaSets->count() : 0),
            'isStarred' => \Illuminate\Support\Facades\Auth::check() && $this->relationLoaded('starredByUsers')
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
