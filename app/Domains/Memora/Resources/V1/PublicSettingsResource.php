<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicSettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'branding' => [
                // Only include public branding information
                'logoUrl' => $this->whenLoaded('logo', fn () => $this->logo?->url),
                'faviconUrl' => $this->whenLoaded('favicon', fn () => $this->favicon?->url),
                'name' => $this->branding_name,
                'website' => $this->branding_website,
                'location' => $this->branding_location,
                'tagline' => $this->branding_tagline,
                'description' => $this->branding_description,
                // Exclude sensitive data: domain, customDomain, logoUuid, faviconUuid, 
                // showMazelootBranding, supportEmail, supportPhone, address fields, 
                // businessHours, contactName, taxVatId, foundedYear, industry
            ],
            // Exclude: preference, homepage, email (all contain sensitive data)
        ];
    }
}

