<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaSetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'count' => $this->whenCounted('media', $this->media_count ?? 0),
            'order' => $this->order,
        ];
    }
}

