<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'branding' => [
                'domain' => $this->branding_domain,
                'customDomain' => $this->branding_custom_domain,
                'logoUuid' => $this->branding_logo_uuid,
                'faviconUuid' => $this->branding_favicon_uuid,
                'logoUrl' => $this->whenLoaded('logo', fn () => $this->logo?->url),
                'faviconUrl' => $this->whenLoaded('favicon', fn () => $this->favicon?->url),
                'showMazelootBranding' => $this->branding_show_mazeloot_branding,
            ],
            'preference' => [
                'filenameDisplay' => $this->preference_filename_display,
                'searchEngineVisibility' => $this->preference_search_engine_visibility,
                'sharpeningLevel' => $this->preference_sharpening_level,
                'rawPhotoSupport' => $this->preference_raw_photo_support,
                'termsOfService' => $this->preference_terms_of_service,
                'privacyPolicy' => $this->preference_privacy_policy,
                'enableCookieBanner' => $this->preference_enable_cookie_banner,
                'language' => $this->preference_language,
                'timezone' => $this->preference_timezone,
            ],
            'homepage' => [
                'status' => $this->homepage_status,
                'password' => $this->homepage_password,
                'biography' => $this->homepage_biography,
                'info' => $this->homepage_info ?? [],
            ],
            'email' => [
                'fromName' => $this->email_from_name,
                'fromAddress' => $this->email_from_address,
                'replyTo' => $this->email_reply_to,
            ],
        ];
    }
}
