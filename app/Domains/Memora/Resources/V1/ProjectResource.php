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
            'presetId' => $this->preset_uuid,
            'watermarkId' => $this->watermark_uuid,
            'color' => $this->color,
            // Extract eventDate from settings if it exists
            'date' => $this->settings['eventDate'] ?? null,
            'eventDate' => $this->settings['eventDate'] ?? null,
            // Preview images from phase cover photos (up to 4)
            'previewImages' => $this->getPreviewImages(),
            'isStarred' => Auth::check() && $this->relationLoaded('starredByUsers') 
                ? $this->starredByUsers->isNotEmpty() 
                : false,
        ];
    }

    /**
     * Get preview images from all phase cover photos
     * Returns up to 4 cover photo URLs
     *
     * @return array
     */
    private function getPreviewImages(): array
    {
        $previewImages = [];

        // Collect cover photos from selections
        if ($this->relationLoaded('selections')) {
            foreach ($this->selections as $selection) {
                if ($selection->cover_photo_url) {
                    $previewImages[] = $selection->cover_photo_url;
                    if (count($previewImages) >= 4) {
                        break;
                    }
                }
            }
        }

        return $previewImages;
    }
}

