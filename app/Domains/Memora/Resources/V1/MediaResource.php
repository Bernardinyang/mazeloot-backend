<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->project_id,
            'phase' => $this->phase,
            'phaseId' => $this->phase_id,
            'collectionId' => $this->collection_id,
            'setId' => $this->set_id,
            'isSelected' => $this->is_selected,
            'selectedAt' => $this->selected_at?->toIso8601String(),
            'revisionNumber' => $this->revision_number,
            'feedback' => $this->whenLoaded('feedback', function () {
                return $this->feedback->map(fn($f) => [
                    'id' => $f->id,
                    'type' => $f->type,
                    'content' => $f->content,
                    'createdAt' => $f->created_at->toIso8601String(),
                    'createdBy' => $f->created_by,
                ]);
            }, []),
            'isCompleted' => $this->is_completed,
            'originalMediaId' => $this->original_media_id,
            'lowResCopyUrl' => $this->low_res_copy_url,
            'url' => $this->url,
            'thumbnail' => $this->thumbnail,
            'type' => $this->type,
            'filename' => $this->filename,
            'order' => $this->order,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}

