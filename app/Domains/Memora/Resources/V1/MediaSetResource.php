<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaSetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'count' => $this->whenCounted('media', $this->media_count ?? 0),
            'order' => $this->order,
            'selectionLimit' => $this->selection_limit,
            'selectionUuid' => $this->selection_uuid,
            'proofUuid' => $this->proof_uuid,
            'media' => $this->whenLoaded('media', function () {
                return MediaResource::collection($this->media);
            }, []),
            'selection' => $this->whenLoaded('selection', function () {
                return new SelectionResource($this->selection);
            }, null),
            'proofing' => $this->whenLoaded('proofing', function () {
                return new ProofingResource($this->proofing);
            }, null),
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }, null),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
