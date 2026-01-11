<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraWatermark;
use App\Services\Image\ImageUploadService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class WatermarkService
{
    protected ImageUploadService $imageUploadService;
    protected NotificationService $notificationService;

    public function __construct(
        ImageUploadService $imageUploadService,
        NotificationService $notificationService
    ) {
        $this->imageUploadService = $imageUploadService;
        $this->notificationService = $notificationService;
    }

    /**
     * Convert rgba() color to hex format
     */
    protected function convertColorToHex(?string $color): ?string
    {
        if (! $color || $color === 'transparent') {
            return null;
        }

        // Already hex format
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return $color;
        }

        // Convert rgba() to hex
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/', $color, $matches)) {
            $r = (int) $matches[1];
            $g = (int) $matches[2];
            $b = (int) $matches[3];
            $alpha = isset($matches[4]) ? (float) $matches[4] : 1.0;

            // If alpha is less than 1, we can't represent it in hex, so use the RGB values
            // For now, we'll just use the RGB values (treating alpha as opaque)
            $hex = sprintf('#%02x%02x%02x', $r, $g, $b);

            return $hex;
        }

        // Return as-is if we can't convert (might cause issues, but better than failing)
        return strlen($color) <= 7 ? $color : null;
    }

    /**
     * Get all watermarks for the authenticated user
     */
    public function getByUser(): Collection
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraWatermark::where('user_uuid', $user->uuid)
            ->with('imageFile')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get single watermark by ID (user-scoped)
     */
    public function getById(string $id): MemoraWatermark
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        return MemoraWatermark::where('user_uuid', $user->uuid)
            ->with('imageFile')
            ->findOrFail($id);
    }

    /**
     * Create a watermark
     */
    public function create(array $data): MemoraWatermark
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $watermarkData = [
            'user_uuid' => $user->uuid,
            'name' => $data['name'],
            'type' => $data['type'],
            'scale' => $data['scale'] ?? ($data['type'] === 'image' ? 100 : 50),
            'opacity' => $data['opacity'] ?? 80,
            'position' => $data['position'] ?? 'bottom-right',
        ];

        if (isset($data['imageFileUuid'])) {
            $watermarkData['image_file_uuid'] = $data['imageFileUuid'];
        }

        if (isset($data['text'])) {
            $watermarkData['text'] = $data['text'];
        }
        if (isset($data['fontFamily'])) {
            $watermarkData['font_family'] = $data['fontFamily'];
        }
        if (isset($data['fontStyle'])) {
            $watermarkData['font_style'] = $data['fontStyle'];
        }
        if (isset($data['fontColor'])) {
            $watermarkData['font_color'] = $this->convertColorToHex($data['fontColor']);
        }
        if (isset($data['backgroundColor'])) {
            $watermarkData['background_color'] = $this->convertColorToHex($data['backgroundColor']);
        }
        if (isset($data['lineHeight'])) {
            $watermarkData['line_height'] = $data['lineHeight'];
        }
        if (isset($data['letterSpacing'])) {
            $watermarkData['letter_spacing'] = $data['letterSpacing'];
        }
        if (isset($data['padding'])) {
            $watermarkData['padding'] = $data['padding'];
        }
        if (isset($data['textTransform'])) {
            $watermarkData['text_transform'] = $data['textTransform'];
        }
        if (isset($data['borderRadius'])) {
            $watermarkData['border_radius'] = $data['borderRadius'];
        }
        if (isset($data['borderWidth'])) {
            $watermarkData['border_width'] = $data['borderWidth'];
        }
        if (isset($data['borderColor'])) {
            $watermarkData['border_color'] = $this->convertColorToHex($data['borderColor']);
        }
        if (isset($data['borderStyle'])) {
            $watermarkData['border_style'] = $data['borderStyle'];
        }

        $watermark = MemoraWatermark::create($watermarkData);
        $watermark->load('imageFile');

        // Create notification
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'watermark_created',
            'Watermark Created',
            "Watermark '{$watermark->name}' has been created successfully.",
            "Your new watermark '{$watermark->name}' is now available to use.",
            '/memora/settings/watermark'
        );

        return $watermark;
    }

    /**
     * Update a watermark
     */
    public function update(string $id, array $data): MemoraWatermark
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $watermark = MemoraWatermark::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['type'])) {
            $updateData['type'] = $data['type'];
        }
        if (isset($data['scale'])) {
            $updateData['scale'] = $data['scale'];
        }
        if (isset($data['opacity'])) {
            $updateData['opacity'] = $data['opacity'];
        }
        if (isset($data['position'])) {
            $updateData['position'] = $data['position'];
        }

        if (array_key_exists('imageFileUuid', $data)) {
            $updateData['image_file_uuid'] = $data['imageFileUuid'];
        }

        if (array_key_exists('text', $data)) {
            $updateData['text'] = $data['text'];
        }
        if (array_key_exists('fontFamily', $data)) {
            $updateData['font_family'] = $data['fontFamily'] ?: null;
        }
        if (array_key_exists('fontStyle', $data)) {
            $updateData['font_style'] = $data['fontStyle'] ?: null;
        }
        if (array_key_exists('fontColor', $data)) {
            $updateData['font_color'] = $data['fontColor'] ? $this->convertColorToHex($data['fontColor']) : null;
        }
        if (array_key_exists('backgroundColor', $data)) {
            $updateData['background_color'] = $data['backgroundColor'] ? $this->convertColorToHex($data['backgroundColor']) : null;
        }
        if (array_key_exists('lineHeight', $data)) {
            $updateData['line_height'] = $data['lineHeight'];
        }
        if (array_key_exists('letterSpacing', $data)) {
            $updateData['letter_spacing'] = $data['letterSpacing'];
        }
        if (array_key_exists('padding', $data)) {
            $updateData['padding'] = $data['padding'];
        }
        if (array_key_exists('textTransform', $data)) {
            $updateData['text_transform'] = $data['textTransform'] ?: null;
        }
        if (array_key_exists('borderRadius', $data)) {
            $updateData['border_radius'] = $data['borderRadius'];
        }
        if (array_key_exists('borderWidth', $data)) {
            $updateData['border_width'] = $data['borderWidth'];
        }
        if (array_key_exists('borderColor', $data)) {
            $updateData['border_color'] = $data['borderColor'] ? $this->convertColorToHex($data['borderColor']) : null;
        }
        if (array_key_exists('borderStyle', $data)) {
            $updateData['border_style'] = $data['borderStyle'] ?: null;
        }

        $watermark->update($updateData);
        $watermark->refresh();
        $watermark->load('imageFile');

        // Create notification
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'watermark_updated',
            'Watermark Updated',
            "Watermark '{$watermark->name}' has been updated successfully.",
            "Your watermark '{$watermark->name}' settings have been saved.",
            '/memora/settings/watermark'
        );

        return $watermark;
    }

    /**
     * Delete a watermark
     */
    public function delete(string $id): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $watermark = MemoraWatermark::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        $name = $watermark->name;
        $deleted = $watermark->delete();

        if ($deleted) {
            // Create notification
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'watermark_deleted',
                'Watermark Deleted',
                "Watermark '{$name}' has been deleted.",
                "The watermark '{$name}' has been permanently removed.",
                '/memora/settings/watermark'
            );
        }

        return $deleted;
    }

    /**
     * Upload watermark image
     */
    public function uploadImage($file): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $uploadResult = $this->imageUploadService->uploadImage($file, [
            'context' => 'watermark',
            'visibility' => 'public',
        ]);

        // Store file in user_files table
        $totalSizeWithVariants = $uploadResult['meta']['total_size_with_variants'] ?? $file->getSize();
        $userFile = \App\Models\UserFile::create([
            'user_uuid' => $user->uuid,
            'url' => $uploadResult['variants']['original'] ?? $uploadResult['variants']['large'] ?? '',
            'path' => 'uploads/images/'.$uploadResult['uuid'],
            'type' => 'image',
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $uploadResult['meta']['width'] ?? null,
            'height' => $uploadResult['meta']['height'] ?? null,
            'metadata' => [
                'uuid' => $uploadResult['uuid'],
                'variants' => $uploadResult['variants'],
                'variant_sizes' => $uploadResult['variant_sizes'] ?? [],
                'total_size_with_variants' => $totalSizeWithVariants,
            ],
        ]);

        // Update cached storage
        $storageService = app(\App\Services\Storage\UserStorageService::class);
        $storageService->incrementStorage($user->uuid, $totalSizeWithVariants);

        return [
            'url' => $userFile->url,
            'uuid' => $userFile->uuid,
        ];
    }

    /**
     * Duplicate a watermark
     */
    public function duplicate(string $id): MemoraWatermark
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $original = MemoraWatermark::where('user_uuid', $user->uuid)
            ->with('imageFile')
            ->findOrFail($id);

        $duplicateData = $original->toArray();
        unset($duplicateData['uuid'], $duplicateData['id'], $duplicateData['created_at'], $duplicateData['updated_at']);
        $duplicateData['name'] = $original->name.' (Copy)';
        $duplicateData['user_uuid'] = $user->uuid;

        $duplicate = MemoraWatermark::create($duplicateData);
        $duplicate->load('imageFile');

        return $duplicate;
    }

    /**
     * Get watermark usage count (how many media items use this watermark)
     */
    public function getUsageCount(string $id): int
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $watermark = MemoraWatermark::where('user_uuid', $user->uuid)
            ->findOrFail($id);

        return \App\Domains\Memora\Models\MemoraMedia::where('watermark_uuid', $watermark->uuid)
            ->count();
    }
}
