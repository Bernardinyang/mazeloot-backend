<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'projectId' => $this->project_uuid,
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }, null),
            'coverLayout' => $this->whenLoaded('coverLayout', function () {
                return new CoverLayoutResource($this->coverLayout);
            }, null),
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}

