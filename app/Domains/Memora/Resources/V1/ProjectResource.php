<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend API contract
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'preset' => $this->whenLoaded('preset', function () {
                return new PresetResource($this->preset);
            }, null),
            'watermark' => $this->whenLoaded('watermark', function () {
                return new WatermarkResource($this->watermark);
            }, null),
            'mediaSets' => $this->whenLoaded('mediaSets', function () {
                return MediaSetResource::collection($this->mediaSets);
            }, []),
            'selections' => $this->whenLoaded('selections', function () {
                return SelectionResource::collection($this->selections);
            }, []),
            'proofing' => $this->whenLoaded('proofing', function () {
                return ProofingResource::collection($this->proofing);
            }, []),
            'collections' => $this->whenLoaded('collections', function () {
                return CollectionResource::collection($this->collections);
            }, []),
            'settings' => $this->settings ?? [],
            'hasSelections' => $this->has_selections,
            'hasProofing' => $this->has_proofing,
            'hasCollections' => $this->has_collections,
            'parentId' => $this->parent_id,
            'presetId' => $this->preset_id,
            'watermarkId' => $this->watermark_id,
        ];
    }
}

