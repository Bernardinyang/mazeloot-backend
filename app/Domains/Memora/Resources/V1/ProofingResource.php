<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProofingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->project_id,
            'name' => $this->name,
            'status' => $this->status,
            'maxRevisions' => $this->max_revisions,
            'currentRevision' => $this->current_revision,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->when(isset($this->media_count), $this->media_count),
            'completedCount' => $this->when(isset($this->completed_count), $this->completed_count),
            'pendingCount' => $this->when(isset($this->pending_count), $this->pending_count),
        ];
    }
}

