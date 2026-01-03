<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CollectionResource extends JsonResource
{
    /**
     * Normalize coverDesign to ensure proper structure
     */
    private function normalizeCoverDesign(array $coverDesign): array
    {
        $defaults = [
            'coverLayoutUuid' => null,
            'coverFocalPoint' => ['x' => 50, 'y' => 50],
        ];

        $normalized = array_merge($defaults, $coverDesign);

        // Ensure coverFocalPoint is always an array with x and y, preserving float values
        if (isset($coverDesign['coverFocalPoint'])) {
            if (is_array($coverDesign['coverFocalPoint'])) {
                $normalized['coverFocalPoint'] = [
                    'x' => isset($coverDesign['coverFocalPoint']['x'])
                        ? (float) $coverDesign['coverFocalPoint']['x']
                        : 50,
                    'y' => isset($coverDesign['coverFocalPoint']['y'])
                        ? (float) $coverDesign['coverFocalPoint']['y']
                        : 50,
                ];
            } else {
                $normalized['coverFocalPoint'] = ['x' => 50, 'y' => 50];
            }
        }

        return $normalized;
    }

    /**
     * Organize settings by section (general, privacy, download, favorite, design)
     * Reads from organized DB structure or falls back to flat fields for backward compatibility
     */
    private function organizeSettings(array $settings): array
    {
        $organized = [];

        // Preserve metadata fields
        if (isset($settings['eventDate'])) {
            $organized['eventDate'] = $settings['eventDate'];
        }
        if (isset($settings['display_settings'])) {
            $organized['display_settings'] = $settings['display_settings'];
        }
        if (isset($settings['thumbnail'])) {
            $organized['thumbnail'] = $settings['thumbnail'];
        }
        if (isset($settings['image'])) {
            $organized['image'] = $settings['image'];
        }

        // General settings - use organized structure if exists, otherwise build from flat
        if (isset($settings['general']) && is_array($settings['general'])) {
            $organized['general'] = $settings['general'];
            // Remove slideshowOptions if it exists
            unset($organized['general']['slideshowOptions']);
        } else {
            $organized['general'] = [
                'url' => $settings['url'] ?? null,
                'tags' => $settings['tags'] ?? [],
                'emailRegistration' => $settings['emailRegistration'] ?? false,
                'galleryAssist' => $settings['galleryAssist'] ?? false,
                'slideshow' => $settings['slideshow'] ?? true,
                'slideshowSpeed' => $settings['slideshowSpeed'] ?? 'regular',
                'slideshowAutoLoop' => $settings['slideshowAutoLoop'] ?? true,
                'socialSharing' => $settings['socialSharing'] ?? true,
                'language' => $settings['language'] ?? 'en',
                'autoExpiryDate' => $settings['autoExpiryDate'] ?? null,
                'expiryDate' => $settings['expiryDate'] ?? null,
                'expiryDays' => $settings['expiryDays'] ?? null,
            ];
        }

        // Privacy settings - use organized structure if exists, otherwise build from flat
        if (isset($settings['privacy']) && is_array($settings['privacy'])) {
            $organized['privacy'] = $settings['privacy'];
        } else {
            $organized['privacy'] = [
                'collectionPassword' => ! empty($settings['password']),
                'showOnHomepage' => $settings['showOnHomepage'] ?? false,
                'clientExclusiveAccess' => $settings['clientExclusiveAccess'] ?? false,
                'allowClientsMarkPrivate' => $settings['allowClientsMarkPrivate'] ?? false,
                'clientOnlySets' => $settings['clientOnlySets'] ?? null,
            ];
        }

        // Download settings - use organized structure if exists, otherwise build from flat
        if (isset($settings['download']) && is_array($settings['download'])) {
            $organized['download'] = $settings['download'];
        } else {
            $organized['download'] = [
                'photoDownload' => $settings['photoDownload'] ?? true,
                'highResolution' => [
                    'enabled' => $settings['highResolutionEnabled'] ?? false,
                    'size' => $settings['highResolutionSize'] ?? '3600px',
                ],
                'webSize' => [
                    'enabled' => $settings['webSizeEnabled'] ?? false,
                    'size' => $settings['webSize'] ?? '1024px',
                ],
                'videoDownload' => $settings['videoDownload'] ?? false,
                'downloadPin' => $settings['downloadPin'] ?? null,
                'downloadPinEnabled' => $settings['downloadPinEnabled'] ?? false,
                'limitDownloads' => $settings['limitDownloads'] ?? false,
                'downloadLimit' => $settings['downloadLimit'] ?? 1,
                'restrictToContacts' => $settings['restrictToContacts'] ?? false,
                'allowedDownloadEmails' => $settings['allowedDownloadEmails'] ?? null,
                'downloadableSets' => $settings['downloadableSets'] ?? null,
            ];
        }

        // Favorite settings - use organized structure if exists, otherwise build from flat
        if (isset($settings['favorite']) && is_array($settings['favorite'])) {
            $organized['favorite'] = $settings['favorite'];
        } else {
            $organized['favorite'] = [
                'enabled' => $settings['favoriteEnabled'] ?? true,
                'photos' => $settings['favoritePhotos'] ?? true,
                'notes' => $settings['favoriteNotes'] ?? true,
            ];
        }

        // Design settings - use organized structure if exists, otherwise build from flat
        if (isset($settings['design']) && is_array($settings['design'])) {
            $organized['design'] = [
                'cover' => $this->normalizeCoverDesign($settings['design']['cover'] ?? []),
                'grid' => array_merge([
                    'gridStyle' => 'classic',
                    'gridColumns' => 3,
                    'thumbnailOrientation' => 'medium',
                    'gridSpacing' => 'normal',
                    'tabStyle' => 'icon-text',
                ], $settings['design']['grid'] ?? []),
                'typography' => array_merge([
                    'fontFamily' => 'sans',
                    'fontStyle' => 'normal',
                ], $settings['design']['typography'] ?? []),
                'color' => array_merge([
                    'colorPalette' => 'light',
                ], $settings['design']['color'] ?? []),
            ];
        } else {
            $organized['design'] = [
                'cover' => $this->normalizeCoverDesign($settings['coverDesign'] ?? []),
                'grid' => array_merge([
                    'gridStyle' => 'classic',
                    'gridColumns' => 3,
                    'thumbnailOrientation' => 'medium',
                    'gridSpacing' => 'normal',
                    'tabStyle' => 'icon-text',
                ], $settings['gridDesign'] ?? []),
                'typography' => array_merge([
                    'fontFamily' => 'sans',
                    'fontStyle' => 'normal',
                ], $settings['typographyDesign'] ?? []),
                'color' => array_merge([
                    'colorPalette' => 'light',
                ], $settings['colorDesign'] ?? []),
            ];
        }

        return $organized;
    }

    public function toArray($request): array
    {
        $settings = $this->organizeSettings($this->settings ?? []);

        return [
            'id' => $this->uuid,
            'userId' => $this->user_uuid,
            'folderId' => $this->folder_uuid,
            'projectId' => $this->project_uuid,
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }, null),
            'presetId' => $this->preset_uuid,
            'preset' => $this->whenLoaded('preset', function () {
                return new PresetResource($this->preset);
            }, null),
            'watermarkId' => $this->watermark_uuid,
            'watermark' => $this->whenLoaded('watermark', function () {
                return new WatermarkResource($this->watermark);
            }, null),
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'color' => $this->color,
            'thumbnail' => $settings['thumbnail'] ?? null,
            'image' => $settings['image'] ?? null,
            'eventDate' => $settings['eventDate'] ?? null,
            'settings' => $settings,
            'isStarred' => Auth::check() && $this->relationLoaded('starredByUsers')
                ? $this->starredByUsers->isNotEmpty()
                : false,
            'mediaCount' => $this->media_count ?? ($this->relationLoaded('mediaSets') && $this->mediaSets->isNotEmpty()
                ? $this->mediaSets->sum(fn ($set) => $set->media_count ?? 0)
                : 0),
            'setCount' => $this->set_count ?? ($this->relationLoaded('mediaSets') ? $this->mediaSets->count() : 0),
            'mediaSets' => $this->whenLoaded('mediaSets', function () {
                return MediaSetResource::collection($this->mediaSets);
            }, []),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
