<?php

namespace App\Services\Image;

use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class ImageVariantGenerator
{
    protected const JPEG_QUALITY_MIN = 75;

    protected const JPEG_QUALITY_MAX = 82;

    /**
     * Generate all image variants
     *
     * @param  string  $originalPath  Temporary path to original image
     * @param  string  $outputDir  Directory to save variants
     * @param  string|null  $originalExtension  Original file extension (if known)
     * @return array<string, string> Map of variant name => file path
     */
    public function generateVariants(string $originalPath, string $outputDir, ?string $originalExtension = null): array
    {
        $variants = [];
        $originalDims = $this->getDimensions($originalPath);

        // Get extension - prefer provided extension, fallback to pathinfo
        $extension = $originalExtension ?: pathinfo($originalPath, PATHINFO_EXTENSION);
        if (empty($extension)) {
            // Last resort: try to detect from MIME type
            $mimeType = mime_content_type($originalPath);
            $extension = match (true) {
                str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') => 'jpg',
                str_contains($mimeType, 'png') => 'png',
                str_contains($mimeType, 'webp') => 'webp',
                str_contains($mimeType, 'gif') => 'gif',
                default => 'jpg',
            };
        }

        // Original (copy as-is, no modifications)
        $originalVariant = $outputDir.'/original.'.strtolower($extension);
        copy($originalPath, $originalVariant);
        $variants['original'] = $originalVariant;

        // Large: max width 2000px (Fit::Max prevents upscaling)
        $largeVariant = $this->saveVariant($originalPath, $outputDir, 'large', function (Image $image) {
            $image->fit(Fit::Max, 2000, 2000)
                ->optimize();
        });
        $variants['large'] = $largeVariant;

        // Medium: max width 1200px (Fit::Max prevents upscaling)
        $mediumVariant = $this->saveVariant($originalPath, $outputDir, 'medium', function (Image $image) {
            $image->fit(Fit::Max, 1200, 1200)
                ->optimize();
        });
        $variants['medium'] = $mediumVariant;

        // Thumb: max width 400px (Fit::Max prevents upscaling)
        $thumbVariant = $this->saveVariant($originalPath, $outputDir, 'thumb', function (Image $image) {
            $image->fit(Fit::Max, 400, 400)
                ->optimize();
        });
        $variants['thumb'] = $thumbVariant;

        return $variants;
    }

    /**
     * Save a single variant with transformation
     *
     * @return string Output file path
     */
    protected function saveVariant(string $originalPath, string $outputDir, string $variantName, callable $transform): string
    {
        $outputPath = $outputDir.'/'.$variantName.'.jpg';

        $image = Image::load($originalPath);

        // Preserve EXIF orientation
        $image->useImageDriver(config('image.driver', 'gd'));

        // Apply transformations
        $transform($image);

        // Determine if we need transparency (PNG/WebP with alpha)
        $needsTransparency = $this->needsTransparency($originalPath);

        if ($needsTransparency) {
            // Save as PNG to preserve transparency
            $outputPath = str_replace('.jpg', '.png', $outputPath);
            $image->save($outputPath);
        } else {
            // Convert to JPEG with quality settings
            $quality = random_int(self::JPEG_QUALITY_MIN, self::JPEG_QUALITY_MAX);
            $image->quality($quality)
                ->format('jpg')
                ->save($outputPath);
        }

        return $outputPath;
    }

    /**
     * Check if image needs transparency preservation
     */
    protected function needsTransparency(string $imagePath): bool
    {
        $mimeType = mime_content_type($imagePath);

        if ($mimeType === 'image/png') {
            // Check if PNG has alpha channel
            $imageInfo = @getimagesize($imagePath);
            if ($imageInfo && isset($imageInfo['channels']) && $imageInfo['channels'] === 4) {
                return true;
            }
        }

        if ($mimeType === 'image/webp') {
            // WebP can have transparency
            $imageInfo = @getimagesize($imagePath);
            if ($imageInfo && isset($imageInfo['channels']) && $imageInfo['channels'] === 4) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get image dimensions
     *
     * @return array{width: int, height: int}
     */
    public function getDimensions(string $imagePath): array
    {
        $imageInfo = @getimagesize($imagePath);

        if ($imageInfo === false) {
            return ['width' => 0, 'height' => 0];
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }
}
