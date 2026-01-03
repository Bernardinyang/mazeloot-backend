<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PresetResource extends JsonResource
{
    public function toArray($request): array
    {
        // Ensure we have a valid resource
        if (! $this->resource) {
            return [];
        }

        // Calculate usage count if not already loaded
        $usageCount = isset($this->usage_count)
            ? (int) $this->usage_count
            : 0;

        // Calculate completeness score
        $presetService = app(\App\Domains\Memora\Services\PresetService::class);
        $completenessScore = $presetService->getCompletenessScore($this->resource);

        return [
            'id' => $this->resource->uuid ?? null,
            'name' => $this->resource->name ?? '',
            'description' => $this->resource->description ?? null,
            'category' => $this->resource->category ?? null,
            'isSelected' => (bool) ($this->resource->is_selected ?? false),
            'usageCount' => $usageCount,
            'completenessScore' => $completenessScore,
            'collectionTags' => $this->resource->collection_tags ?? null,
            'photoSets' => $this->resource->photo_sets ?? ['Highlights'],
            'defaultWatermarkId' => $this->resource->default_watermark_uuid ?? null,
            'defaultWatermark' => $this->whenLoaded('defaultWatermark', function () {
                return new WatermarkResource($this->defaultWatermark);
            }, null),
            'emailRegistration' => (bool) ($this->resource->email_registration ?? false),
            'galleryAssist' => (bool) ($this->resource->gallery_assist ?? false),
            'slideshow' => (bool) ($this->resource->slideshow ?? true),
            'slideshowSpeed' => $this->resource->slideshow_speed ?? 'regular',
            'slideshowAutoLoop' => (bool) ($this->resource->slideshow_auto_loop ?? true),
            'socialSharing' => (bool) ($this->resource->social_sharing ?? true),
            'language' => $this->resource->language ?? 'en',
            // Design fields (excluding cover style/focal point)
            'design' => [
                'fontFamily' => $this->resource->design_font_family ?? 'inter',
                'fontStyle' => $this->resource->design_font_style ?? 'regular',
                'colorPalette' => $this->resource->design_color_palette ?? 'light',
                'gridStyle' => $this->resource->design_grid_style ?? 'masonry',
                'gridColumns' => $this->resource->design_grid_columns ?? 3,
                'thumbnailOrientation' => $this->resource->design_thumbnail_orientation ?? 'medium',
                'gridSpacing' => $this->resource->design_grid_spacing ?? 16,
                'tabStyle' => $this->resource->design_tab_style ?? 'icon-text',
                'joyCover' => [
                    'title' => $this->resource->design_joy_cover_title ?? '',
                    'avatar' => $this->resource->design_joy_cover_avatar ?? null,
                    'showDate' => (bool) ($this->resource->design_joy_cover_show_date ?? false),
                    'showName' => (bool) ($this->resource->design_joy_cover_show_name ?? false),
                    'buttonText' => $this->resource->design_joy_cover_button_text ?? 'View Gallery',
                    'showButton' => (bool) ($this->resource->design_joy_cover_show_button ?? false),
                    'backgroundPattern' => $this->resource->design_joy_cover_background_pattern ?? 'crosses',
                ],
            ],
            // Privacy fields
            'privacy' => [
                'collectionPassword' => (bool) ($this->resource->privacy_collection_password ?? false),
                'showOnHomepage' => (bool) ($this->resource->privacy_show_on_homepage ?? false),
                'clientExclusiveAccess' => (bool) ($this->resource->privacy_client_exclusive_access ?? false),
                'allowClientsMarkPrivate' => (bool) ($this->resource->privacy_allow_clients_mark_private ?? false),
                'clientOnlySets' => $this->resource->privacy_client_only_sets ?? null,
            ],
            // Download fields
            'download' => [
                'photoDownload' => (bool) ($this->resource->download_photo_download ?? false),
                'highResolution' => [
                    'enabled' => (bool) ($this->resource->download_high_resolution_enabled ?? false),
                    'size' => $this->resource->download_high_resolution_size ?? '3600px',
                ],
                'webSize' => [
                    'enabled' => (bool) ($this->resource->download_web_size_enabled ?? false),
                    'size' => $this->resource->download_web_size ?? '1920px',
                ],
                'videoDownload' => (bool) ($this->resource->download_video_download ?? false),
                'downloadPin' => $this->resource->download_download_pin ?? null,
                'downloadPinEnabled' => (bool) ($this->resource->download_download_pin_enabled ?? false),
                'limitDownloads' => (bool) ($this->resource->download_limit_downloads ?? false),
                'downloadLimit' => $this->resource->download_download_limit ?? null,
                'restrictToContacts' => (bool) ($this->resource->download_restrict_to_contacts ?? false),
                'downloadableSets' => $this->resource->download_downloadable_sets ?? null,
            ],
            // Favorite fields
            'favorite' => [
                'enabled' => (bool) ($this->resource->favorite_favorite_enabled ?? false),
                'photos' => (bool) ($this->resource->favorite_favorite_photos ?? false),
                'notes' => (bool) ($this->resource->favorite_favorite_notes ?? false),
            ],
            'createdAt' => $this->resource->created_at ? $this->resource->created_at->toIso8601String() : null,
            'updatedAt' => $this->resource->updated_at ? $this->resource->updated_at->toIso8601String() : null,
        ];
    }
}
