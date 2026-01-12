<?php

namespace App\Domains\Memora\Resources\V1;

use App\Services\Storage\UserStorageService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class RawFilesResource extends JsonResource
{
    public function toArray($request): array
    {
        $isOwner = Auth::check() && Auth::user()->uuid === $this->user_uuid;

        $password = $isOwner ? $this->getAttribute('password') : null;

        $settings = $this->settings ?? [];
        $downloadSettings = $settings['download'] ?? [];

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
            'password' => $password,
            'allowedEmails' => $this->allowed_emails ?? [],
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'mediaCount' => $this->media_count ?? 0,
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
            'downloadPinEnabled' => $downloadSettings['downloadPinEnabled'] ?? false,
            'downloadPin' => $isOwner ? ($downloadSettings['downloadPin'] ?? null) : null,
            'limitDownloads' => $downloadSettings['limitDownloads'] ?? false,
            'downloadLimit' => $downloadSettings['downloadLimit'] ?? 1,
            'settings' => $settings,
        ];
    }

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
            if (! isset($design['typography']) || empty($design['typography'])) {
                $design['typography'] = $defaults['typography'];
            } else {
                $design['typography'] = array_merge($defaults['typography'], $design['typography']);
            }

            return $design;
        }

        return $defaults;
    }

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

    private function getStorageUsed(): int
    {
        try {
            $storageService = app(UserStorageService::class);

            return $storageService->getPhaseStorageUsed($this->uuid, 'raw_files');
        } catch (\Exception $e) {
            return 0;
        }
    }
}
