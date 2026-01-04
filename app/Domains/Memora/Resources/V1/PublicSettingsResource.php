<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicSettingsResource extends JsonResource
{
    private function formatAddress(): ?string
    {
        $parts = array_filter([
            $this->branding_address_street,
            $this->branding_address_city,
            $this->branding_address_state,
            $this->branding_address_zip,
            $this->branding_address_country,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }

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
            'homepage' => [
                'status' => $this->homepage_status,
                'biography' => $this->homepage_biography,
                'info' => $this->homepage_info ?? [],
                'slideshowEnabled' => $this->homepage_slideshow_enabled ?? false,
                // Exclude: password (sensitive data)
            ],
            'contact' => [
                'email' => $this->branding_support_email,
                'phone' => $this->branding_support_phone,
                'address' => $this->formatAddress(),
            ],
            // Exclude: preference, email (all contain sensitive data)
        ];
    }
}
