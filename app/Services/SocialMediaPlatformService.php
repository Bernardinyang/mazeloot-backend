<?php

namespace App\Services;

use App\Models\SocialMediaPlatform;
use Illuminate\Support\Collection;

class SocialMediaPlatformService
{
    public function getAll(): Collection
    {
        return SocialMediaPlatform::ordered()->get();
    }

    public function getActive(): Collection
    {
        return SocialMediaPlatform::active()->ordered()->get();
    }

    public function create(array $data): SocialMediaPlatform
    {
        return SocialMediaPlatform::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'icon' => $data['icon'] ?? null,
            'base_url' => $data['baseUrl'] ?? $data['base_url'] ?? null,
            'is_active' => $data['isActive'] ?? $data['is_active'] ?? true,
            'order' => $data['order'] ?? null,
        ]);
    }

    public function update(string $id, array $data): SocialMediaPlatform
    {
        $platform = SocialMediaPlatform::findOrFail($id);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['slug'])) {
            $updateData['slug'] = $data['slug'];
        }

        if (array_key_exists('icon', $data)) {
            $updateData['icon'] = $data['icon'];
        }

        if (array_key_exists('baseUrl', $data) || array_key_exists('base_url', $data)) {
            $updateData['base_url'] = $data['baseUrl'] ?? $data['base_url'];
        }

        if (array_key_exists('isActive', $data) || array_key_exists('is_active', $data)) {
            $updateData['is_active'] = $data['isActive'] ?? $data['is_active'];
        }

        if (array_key_exists('order', $data)) {
            $updateData['order'] = $data['order'];
        }

        $platform->update($updateData);
        $platform->refresh();

        return $platform;
    }

    public function delete(string $id): bool
    {
        $platform = SocialMediaPlatform::findOrFail($id);

        return $platform->delete();
    }

    public function toggleActive(string $id): SocialMediaPlatform
    {
        $platform = SocialMediaPlatform::findOrFail($id);
        $platform->is_active = ! $platform->is_active;
        $platform->save();
        $platform->refresh();

        return $platform;
    }
}
