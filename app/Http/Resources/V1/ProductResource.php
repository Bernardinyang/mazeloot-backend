<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'displayName' => $this->display_name, // For frontend compatibility
            'description' => $this->description,
            'custom_type' => $this->custom_type,
            'customType' => $this->custom_type, // For frontend compatibility
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'isActive' => $this->is_active, // For frontend compatibility
            'order' => $this->order,
            'metadata' => $this->metadata,
        ];
    }
}
