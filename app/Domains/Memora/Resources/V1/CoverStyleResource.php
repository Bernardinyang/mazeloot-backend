<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CoverStyleResource extends JsonResource
{
    /**
     * Transform the resource into an array matching frontend API contract
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'isActive' => $this->is_active,
            'isDefault' => $this->is_default,
            'config' => $this->config,
            'previewImageUrl' => $this->preview_image_url,
            'order' => $this->order,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}

