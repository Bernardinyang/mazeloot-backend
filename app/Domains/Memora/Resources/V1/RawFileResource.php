<?php

namespace App\Domains\Memora\Resources\V1;

use App\Services\Storage\UserStorageService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class RawFileResource extends JsonResource
{
    public function toArray($request): array
    {
        // Check if the authenticated user is the owner of this raw file
        $isOwner = Auth::check() && Auth::user()->uuid === $this->user_uuid;

        // Get password value (even though it's in $hidden, we can access it via getAttribute)
        $password = $isOwner ? $this->getAttribute('password') : null;

        return [
            'id' => $this->uuid,
            'projectId' => $this->project_uuid,
            'userUuid' => $this->user_uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'color' => $this->color,
            'coverPhotoUrl' => $this->cover_photo_url,
            'coverFocalPoint' => $this->cover_focal_point,
            'hasPassword' => ! empty($this->getAttribute('password')),
            // Only include the actual password if the authenticated user is the owner
            'password' => $password,
            'allowedEmails' => $this->allowed_emails ?? [],
            'rawFileCompletedAt' => $this->raw_file_completed_at?->toIso8601String(),
            'completedByEmail' => $this->completed_by_email,
            'rawFileLimit' => $this->raw_file_limit,
            'resetRawFileLimitAt' => $this->reset_raw_file_limit_at?->toIso8601String(),
            'autoDeleteDate' => $this->auto_delete_date?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->media_count ?? 0,
            'selectedCount' => $this->selected_count ?? 0,
            'setCount' => $this->set_count ?? ($this->relationLoaded('mediaSets') ? $this->mediaSets->count() : 0),
            'storageUsedBytes' => $storageUsed = $this->getStorageUsed(),
            'storageUsedMB' => round($storageUsed / (1024 * 1024), 2),
            'storageUsedGB' => round($storageUsed / (1024 * 1024 * 1024), 2),
            'isStarred' => Auth::check() && $this->relationLoaded('starredByUsers')
                ? $this->starredByUsers->isNotEmpty()
                : false,
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }, null),
            'mediaSets' => $this->whenLoaded('mediaSets', function () {
                return MediaSetResource::collection($this->mediaSets);
            }, []),
            'design' => $this->getDesign(),
            'typographyDesign' => $this->getTypographyDesign(),
            'galleryAssist' => $this->getGalleryAssist(),
            'download' => $this->getDownloadSettings(),
        ];
    }

    /**
     * Get design object from settings
     * Always includes typography defaults
     */
    private function getDesign(): array
    {
        $settings = $this->settings ?? [];
        $defaults = [
            'typography' => [
                'fontFamily' => 'sans',
                'fontStyle' => 'normal',
            ],
        ];

        if (isset($settings['design']) && is_array($settings['design'])) {
            $design = $settings['design'];
            // Ensure typography always has defaults
            if (! isset($design['typography']) || empty($design['typography'])) {
                $design['typography'] = $defaults['typography'];
            } else {
                $design['typography'] = array_merge($defaults['typography'], $design['typography']);
            }

            return $design;
        }

        return $defaults;
    }

    /**
     * Get typographyDesign for backward compatibility
     * Always returns default values if not set
     */
    private function getTypographyDesign(): array
    {
        $settings = $this->settings ?? [];
        $defaults = [
            'fontFamily' => 'sans',
            'fontStyle' => 'normal',
        ];

        if (isset($settings['design']['typography']) && is_array($settings['design']['typography']) && ! empty($settings['design']['typography'])) {
            return array_merge($defaults, $settings['design']['typography']);
        }

        return $defaults;
    }

    /**
     * Get storage used by media in this raw file
     */
    private function getStorageUsed(): int
    {
        try {
            $storageService = app(UserStorageService::class);

            return $storageService->getPhaseStorageUsed($this->uuid, 'raw_file');
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get gallery assist setting from settings
     */
    private function getGalleryAssist(): bool
    {
        $settings = $this->settings ?? [];

        return $settings['galleryAssist'] ?? $settings['general']['galleryAssist'] ?? false;
    }

    /**
     * Get download settings from settings
     * Similar to CollectionResource download settings structure
     */
    private function getDownloadSettings(): array
    {
        $settings = $this->settings ?? [];
        $isOwner = Auth::check() && Auth::user()->uuid === $this->user_uuid;

        // If download settings exist in organized structure, use them
        if (isset($settings['download']) && is_array($settings['download'])) {
            $downloadSettings = $settings['download'];
            // Only include downloadPin if owner
            if (! $isOwner) {
                unset($downloadSettings['downloadPin']);
            }

            return $downloadSettings;
        }

        // Build from flat settings structure
        $downloadSettings = [
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
            'downloadPinEnabled' => ! empty($settings['downloadPin'] ?? null),
            'limitDownloads' => $settings['limitDownloads'] ?? false,
            'downloadLimit' => $settings['downloadLimit'] ?? 1,
            'restrictToContacts' => $settings['restrictToContacts'] ?? false,
            'allowedDownloadEmails' => $settings['allowedDownloadEmails'] ?? null,
            'downloadableSets' => $settings['downloadableSets'] ?? null,
        ];

        // Only include downloadPin if owner
        if ($isOwner && isset($settings['downloadPin'])) {
            $downloadSettings['downloadPin'] = $settings['downloadPin'];
        }

        return $downloadSettings;
    }
}
