<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            'preset' => $this->whenLoaded('preset', function () {
                return new PresetResource($this->preset);
            }, null),
            'watermarkId' => $this->watermark_uuid,
            'watermark' => $this->whenLoaded('watermark', function () {
                return new WatermarkResource($this->watermark);
            }, null),
            'color' => $this->color,
            'hasSelections' => $this->has_selections,
            'hasProofing' => $this->has_proofing,
            'hasCollections' => $this->has_collections,
            'isStarred' => Auth::check() && $this->relationLoaded('starredByUsers')
                ? $this->starredByUsers->isNotEmpty()
                : false,
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
        if (! $this->has_selections) {
            return null;
        }

        try {
            if ($this->relationLoaded('selection')) {
                $selection = $this->getRelation('selection');
            } else {
                $selection = $this->selection;
            }

            if ($selection) {
                return new SelectionResource($selection);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Selection record doesn't exist, return null
        }

        return null;
    }

    /**
     * Get proofing phase
     */
    private function getProofing()
    {
        if (! $this->has_proofing) {
            return null;
        }

        try {
            if ($this->relationLoaded('proofing')) {
                $proofing = $this->getRelation('proofing');
            } else {
                $proofing = $this->proofing;
            }

            if ($proofing) {
                return new ProofingResource($proofing);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Proofing record doesn't exist, return null
        }

        return null;
    }

    /**
     * Get collection phase
     */
    private function getCollection()
    {
        if (! $this->has_collections) {
            return null;
        }

        try {
            if ($this->relationLoaded('collection')) {
                $collection = $this->getRelation('collection');
            } else {
                $collection = $this->collection;
            }

            if ($collection) {
                return new CollectionResource($collection);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Collection record doesn't exist, return null
        }

        return null;
    }
}
