<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SelectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->project_id,
            'name' => $this->name,
            'status' => $this->status,
            'selectionCompletedAt' => $this->selection_completed_at?->toIso8601String(),
            'autoDeleteDate' => $this->auto_delete_date?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->when(isset($this->media_count), $this->media_count),
            'selectedCount' => $this->when(isset($this->selected_count), $this->selected_count),
        ];
    }
}

