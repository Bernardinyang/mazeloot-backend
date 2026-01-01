<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailNotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'notificationType' => $this->notification_type,
            'isEnabled' => $this->is_enabled,
        ];
    }
}
