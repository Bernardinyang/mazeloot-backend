<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraSocialLink;
use App\Models\SocialMediaPlatform;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SocialLinkService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all social links for the authenticated user
     */
    public function getByUser(): Collection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraSocialLink::where('user_uuid', $user->uuid)
            ->with('platform')
            ->orderBy('order')
            ->get();
    }

    /**
     * Get available active platforms for user selection
     */
    public function getAvailablePlatforms(): Collection
    {
        return SocialMediaPlatform::active()->ordered()->get();
    }

    /**
     * Create a social link
     */
    public function create(array $data): MemoraSocialLink
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Validate platform exists and is active
        $platform = SocialMediaPlatform::findOrFail($data['platformUuid'] ?? $data['platform_uuid']);

        if (! $platform->is_active) {
            throw new \RuntimeException('Cannot create link for inactive platform');
        }

        // Get max order for user's links
        $maxOrder = MemoraSocialLink::where('user_uuid', $user->uuid)
            ->max('order') ?? -1;

        $link = MemoraSocialLink::create([
            'user_uuid' => $user->uuid,
            'platform_uuid' => $platform->uuid,
            'url' => $data['url'],
            'is_active' => $data['isActive'] ?? $data['is_active'] ?? true,
            'order' => $data['order'] ?? ($maxOrder + 1),
        ]);

        // Create notification
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'social_link_created',
            'Social Link Added',
            "Social link for {$platform->name} has been added successfully.",
            "Your {$platform->name} link has been added to your homepage.",
            null,
            '/memora/settings/social-links'
        );

        return $link;
    }

    /**
     * Update a social link
     */
    public function update(string $id, array $data): MemoraSocialLink
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $link = MemoraSocialLink::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        $updateData = [];

        if (isset($data['platformUuid']) || isset($data['platform_uuid'])) {
            $platformUuid = $data['platformUuid'] ?? $data['platform_uuid'];
            $platform = SocialMediaPlatform::findOrFail($platformUuid);

            if (! $platform->is_active) {
                throw new \RuntimeException('Cannot update to inactive platform');
            }

            $updateData['platform_uuid'] = $platform->uuid;
        }

        if (isset($data['url'])) {
            $updateData['url'] = $data['url'];
        }

        if (array_key_exists('isActive', $data) || array_key_exists('is_active', $data)) {
            $updateData['is_active'] = $data['isActive'] ?? $data['is_active'];
        }

        if (array_key_exists('order', $data)) {
            $updateData['order'] = $data['order'];
        }

        $link->update($updateData);
        $link->refresh();
        $link->load('platform');

        // Create notification
        $platformName = $link->platform?->name ?? 'Social Media';
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'social_link_updated',
            'Social Link Updated',
            "Social link for {$platformName} has been updated successfully.",
            "Your {$platformName} link has been updated.",
            null,
            '/memora/settings/social-links'
        );

        return $link;
    }

    /**
     * Delete a social link
     */
    public function delete(string $id): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $link = MemoraSocialLink::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        $platformName = $link->platform?->name ?? 'Social Media';
        $deleted = $link->delete();

        if ($deleted) {
            // Create notification
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'social_link_deleted',
                'Social Link Removed',
                "Social link for {$platformName} has been removed.",
                "Your {$platformName} link has been permanently removed from your homepage.",
                null,
                '/memora/settings/social-links'
            );
        }

        return $deleted;
    }

    /**
     * Reorder social links
     */
    public function reorder(array $orderData): Collection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        foreach ($orderData as $index => $linkId) {
            MemoraSocialLink::where('user_uuid', $user->uuid)
                ->where('uuid', $linkId)
                ->update(['order' => $index]);
        }

        return $this->getByUser();
    }
}
