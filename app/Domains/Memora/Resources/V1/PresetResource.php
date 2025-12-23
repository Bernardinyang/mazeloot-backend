<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PresetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'isSelected' => $this->is_selected,
            'collectionTags' => $this->collection_tags,
            'photoSets' => $this->photo_sets,
            'defaultWatermarkId' => $this->default_watermark_uuid,
            'defaultWatermark' => $this->whenLoaded('defaultWatermark', function () {
                return new WatermarkResource($this->defaultWatermark);
            }, null),
            'coverStyle' => $this->whenLoaded('coverStyle', function () {
                return new CoverStyleResource($this->coverStyle);
            }, null),
            'emailRegistration' => $this->email_registration,
            'galleryAssist' => $this->gallery_assist,
            'slideshow' => $this->slideshow,
            'socialSharing' => $this->social_sharing,
            'language' => $this->language,
            // Design fields
            'design' => [
                'coverId' => $this->design_cover_uuid,
                'coverFocalPoint' => $this->design_cover_focal_point,
                'fontFamily' => $this->design_font_family,
                'fontStyle' => $this->design_font_style,
                'colorPalette' => $this->design_color_palette,
                'gridStyle' => $this->design_grid_style,
                'gridColumns' => $this->design_grid_columns,
                'thumbnailSize' => $this->design_thumbnail_size,
                'gridSpacing' => $this->design_grid_spacing,
                'navigationStyle' => $this->design_navigation_style,
                'joyCover' => [
                    'title' => $this->design_joy_cover_title,
                    'avatar' => $this->design_joy_cover_avatar,
                    'showDate' => $this->design_joy_cover_show_date,
                    'showName' => $this->design_joy_cover_show_name,
                    'buttonText' => $this->design_joy_cover_button_text,
                    'showButton' => $this->design_joy_cover_show_button,
                    'backgroundPattern' => $this->design_joy_cover_background_pattern,
                ],
            ],
            // Privacy fields
            'privacy' => [
                'collectionPassword' => $this->privacy_collection_password,
                'showOnHomepage' => $this->privacy_show_on_homepage,
                'clientExclusiveAccess' => $this->privacy_client_exclusive_access,
                'allowClientsMarkPrivate' => $this->privacy_allow_clients_mark_private,
                'clientOnlySets' => $this->privacy_client_only_sets,
            ],
            // Download fields
            'download' => [
                'photoDownload' => $this->download_photo_download,
                'highResolution' => [
                    'enabled' => $this->download_high_resolution_enabled,
                    'size' => $this->download_high_resolution_size,
                ],
                'webSize' => [
                    'enabled' => $this->download_web_size_enabled,
                    'size' => $this->download_web_size,
                ],
                'videoDownload' => $this->download_video_download,
                'downloadPin' => $this->download_download_pin,
                'downloadPinEnabled' => $this->download_download_pin_enabled,
                'limitDownloads' => $this->download_limit_downloads,
                'downloadLimit' => $this->download_download_limit,
                'restrictToContacts' => $this->download_restrict_to_contacts,
                'downloadableSets' => $this->download_downloadable_sets,
            ],
            // Favorite fields
            'favorite' => [
                'enabled' => $this->favorite_favorite_enabled,
                'photos' => $this->favorite_favorite_photos,
                'notes' => $this->favorite_favorite_notes,
            ],
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}

