<?php

namespace App\Domains\Memora\Resources\V1;

use App\Services\Storage\UserStorageService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class SelectionResource extends JsonResource
{
    public function toArray($request): array
    {
        // Check if the authenticated user is the owner of this selection
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
            'selectionCompletedAt' => $this->selection_completed_at?->toIso8601String(),
            'completedByEmail' => $this->completed_by_email,
            'selectionLimit' => $this->selection_limit,
            'resetSelectionLimitAt' => $this->reset_selection_limit_at?->toIso8601String(),
            'autoDeleteDate' => $this->auto_delete_date?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->media_count ?? 0,
            'selectedCount' => $this->selected_count ?? 0,
            'setCount' => $this->set_count ?? ($this->relationLoaded('mediaSets') ? $this->mediaSets->count() : 0),
            'storageUsedBytes' => $this->getStorageUsed(),
            'storageUsedMB' => round($this->getStorageUsed() / (1024 * 1024), 2),
            'storageUsedGB' => round($this->getStorageUsed() / (1024 * 1024 * 1024), 2),
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
     * Get storage used by media in this selection
     */
    private function getStorageUsed(): int
    {
        try {
            $storageService = app(UserStorageService::class);

            return $storageService->getPhaseStorageUsed($this->uuid, 'selection');
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
}
