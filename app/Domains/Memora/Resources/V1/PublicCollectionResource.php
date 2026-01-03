<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicCollectionResource extends JsonResource
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
     * Organize settings by section, excluding sensitive data
     */
    private function organizeSettings(array $settings): array
    {
        $organized = [];

        // Preserve metadata fields
        if (isset($settings['eventDate'])) {
            $organized['eventDate'] = $settings['eventDate'];
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
            ];
        }

        // Privacy settings - exclude sensitive data
        if (isset($settings['privacy']) && is_array($settings['privacy'])) {
            $organized['privacy'] = [
                'collectionPasswordEnabled' => ! empty($settings['privacy']['collectionPasswordEnabled'] ?? $settings['privacy']['password'] ?? null),
                'showOnHomepage' => $settings['privacy']['showOnHomepage'] ?? false,
                'clientExclusiveAccess' => $settings['privacy']['clientExclusiveAccess'] ?? false,
                'allowClientsMarkPrivate' => $settings['privacy']['allowClientsMarkPrivate'] ?? false,
                // Exclude: password, collectionPassword (actual password), clientOnlySets
            ];
        } else {
            $organized['privacy'] = [
                'collectionPasswordEnabled' => ! empty($settings['password'] ?? null),
                'showOnHomepage' => $settings['showOnHomepage'] ?? false,
                'clientExclusiveAccess' => $settings['clientExclusiveAccess'] ?? false,
                'allowClientsMarkPrivate' => $settings['allowClientsMarkPrivate'] ?? false,
            ];
        }

        // Download settings - exclude sensitive data
        if (isset($settings['download']) && is_array($settings['download'])) {
            $organized['download'] = [
                'photoDownload' => $settings['download']['photoDownload'] ?? true,
                'highResolution' => [
                    'enabled' => $settings['download']['highResolution']['enabled'] ?? false,
                    'size' => $settings['download']['highResolution']['size'] ?? '3600px',
                ],
                'webSize' => [
                    'enabled' => $settings['download']['webSize']['enabled'] ?? false,
                    'size' => $settings['download']['webSize']['size'] ?? '1024px',
                ],
                'videoDownload' => $settings['download']['videoDownload'] ?? false,
                'downloadPinEnabled' => $settings['download']['downloadPinEnabled'] ?? false,
                'limitDownloads' => $settings['download']['limitDownloads'] ?? false,
                'downloadLimit' => $settings['download']['downloadLimit'] ?? 1,
                'restrictToContacts' => $settings['download']['restrictToContacts'] ?? false,
                // Exclude: downloadPin, allowedDownloadEmails, downloadableSets
            ];
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
                'downloadPinEnabled' => $settings['downloadPinEnabled'] ?? false,
                'limitDownloads' => $settings['limitDownloads'] ?? false,
                'downloadLimit' => $settings['downloadLimit'] ?? 1,
                'restrictToContacts' => $settings['restrictToContacts'] ?? false,
            ];
        }

        // Favorite settings
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
            // Exclude: userId, folderId, projectId, project
            'presetId' => $this->preset_uuid,
            // Exclude: preset (full resource)
            'watermarkId' => $this->watermark_uuid,
            // Exclude: watermark (full resource)
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'color' => $this->color,
            'thumbnail' => $settings['thumbnail'] ?? null,
            'image' => $settings['image'] ?? null,
            'eventDate' => $settings['eventDate'] ?? null,
            'settings' => $settings,
            // Exclude: isStarred (requires auth)
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
