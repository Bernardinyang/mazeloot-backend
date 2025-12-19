<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CoverLayoutResource extends JsonResource
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
            'layoutConfig' => $this->layoutConfig, // Use accessor to get default config if needed
            'order' => $this->order,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}

