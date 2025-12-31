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
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'presetId' => $this->preset_uuid,
            'watermarkId' => $this->watermark_uuid,
            'color' => $this->color,
            'hasSelections' => $this->has_selections,
            'hasProofing' => $this->has_proofing,
            'hasCollections' => $this->has_collections,
            'selection' => $this->getSelection(),
            'proofing' => $this->getProofing(),
            'collection' => $this->getCollection(),
            // Extract eventDate from settings if it exists
            'eventDate' => $this->settings['eventDate'] ?? null,
        ];
    }

    /**
     * Get selection phase
     */
    private function getSelection()
    {
        // Always try to get selection if has_selections is true
        if ($this->has_selections) {
            // Check if relationship is loaded
            if ($this->relationLoaded('selections') && $this->selections->isNotEmpty()) {
                return new SelectionResource($this->selections->first());
            }
            // If not loaded or empty, try to load it manually
            $selection = $this->selections()->first();

            return $selection ? new SelectionResource($selection) : null;
        }

        return null;
    }

    /**
     * Get proofing phase
     */
    private function getProofing()
    {
        // Always try to get proofing if has_proofing is true
        if ($this->has_proofing) {
            // Check if relationship is loaded
            if ($this->relationLoaded('proofing') && $this->proofing->isNotEmpty()) {
                return new ProofingResource($this->proofing->first());
            }
            // If not loaded or empty, try to load it manually
            $proofing = $this->proofing()->first();

            return $proofing ? new ProofingResource($proofing) : null;
        }

        return null;
    }

    /**
     * Get collection phase
     */
    private function getCollection()
    {
        // Always try to get collection if has_collections is true
        if ($this->has_collections) {
            // Check if relationship is loaded
            if ($this->relationLoaded('collections') && $this->collections->isNotEmpty()) {
                return new CollectionResource($this->collections->first());
            }
            // If not loaded or empty, try to load it manually
            $collection = $this->collections()->first();

            return $collection ? new CollectionResource($collection) : null;
        }

        return null;
    }
}
