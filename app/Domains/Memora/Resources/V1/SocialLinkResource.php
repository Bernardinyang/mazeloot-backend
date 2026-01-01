<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SocialLinkResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'platformUuid' => $this->platform_uuid,
            'platform' => $this->whenLoaded('platform', function () {
                return new \App\Resources\V1\SocialMediaPlatformResource($this->platform);
            }),
            'url' => $this->url,
            'isActive' => $this->is_active,
            'order' => $this->order,
        ];
    }
}
