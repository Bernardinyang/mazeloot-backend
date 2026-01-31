<?php

namespace App\Domains\Memora\Resources\V1;

use App\Services\Storage\UserStorageService;
use App\Support\MemoraFrontendUrls;
use Illuminate\Http\Resources\Json\JsonResource;

class ProofingResource extends JsonResource
{
    public function toArray($request): array
    {
        // Check if the authenticated user is the owner of this proofing
        $isOwner = \Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->uuid === $this->user_uuid;

        // Get password value (even though it's in $hidden, we can access it via getAttribute)
        $password = $isOwner ? $this->getAttribute('password') : null;

        return [
            'id' => $this->uuid,
            'projectId' => $this->project_uuid,
            'userUuid' => $this->user_uuid,
            'brandingDomain' => MemoraFrontendUrls::getBrandingDomainForUser($this->user_uuid),
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
            'primaryEmail' => $this->primary_email,
            'maxRevisions' => $this->max_revisions,
            'currentRevision' => $this->current_revision,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->when(isset($this->media_count), $this->media_count),
            'completedCount' => $this->when(isset($this->completed_count), $this->completed_count),
            'pendingCount' => $this->when(isset($this->pending_count), $this->pending_count),
            'setCount' => $this->when(isset($this->set_count), $this->set_count) ?? ($this->relationLoaded('mediaSets') ? $this->mediaSets->count() : 0),
            'storageUsedBytes' => $storageUsed = $this->getStorageUsed(),
            'storageUsedMB' => round($storageUsed / (1024 * 1024), 2),
            'storageUsedGB' => round($storageUsed / (1024 * 1024 * 1024), 2),
            'isStarred' => \Illuminate\Support\Facades\Auth::check() && $this->relationLoaded('starredByUsers')
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
     * Get storage used by media in this proofing
     */
    private function getStorageUsed(): int
    {
        try {
            $storageService = app(UserStorageService::class);

            return $storageService->getPhaseStorageUsed($this->uuid, 'proofing');
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
