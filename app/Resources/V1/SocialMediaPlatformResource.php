<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SocialMediaPlatformResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'baseUrl' => $this->base_url,
            'isActive' => $this->is_active,
            'order' => $this->order,
        ];
    }
}
