<?php

namespace App\Services\Image;

use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class ImageVariantGenerator
{
    protected const JPEG_QUALITY_MIN = 40;

    protected const JPEG_QUALITY_MAX = 50;

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

        // Large: max width 1400px, reduced quality for viewing but poor download quality
        $largeVariant = $this->saveVariant($originalPath, $outputDir, 'large', function (Image $image) {
            $image->fit(Fit::Max, 1400, 1400)
                ->optimize();
        });
        $variants['large'] = $largeVariant;

        // Medium: max width 800px, reduced quality for viewing but poor download quality
        $mediumVariant = $this->saveVariant($originalPath, $outputDir, 'medium', function (Image $image) {
            $image->fit(Fit::Max, 800, 800)
                ->optimize();
        });
        $variants['medium'] = $mediumVariant;

        // Thumb: max width 400px (Fit::Max prevents upscaling)
        $thumbVariant = $this->saveVariant($originalPath, $outputDir, 'thumb', function (Image $image) {
            $image->fit(Fit::Max, 400, 400)
                ->optimize();
        });
        $variants['thumb'] = $thumbVariant;

        // Preview: watermarked variant for public selection (same size as medium)
        $previewVariant = $this->generatePreviewVariant($originalPath, $outputDir);
        if ($previewVariant) {
            $variants['preview'] = $previewVariant;
        }

        return $variants;
    }

    /**
     * Generate preview variant with "FOR PREVIEW ONLY" watermark
     * Style: Three lines with "PREVIEW" larger and bolder, semi-transparent dark background
     */
    protected function generatePreviewVariant(string $originalPath, string $outputDir): ?string
    {
        try {
            $outputPath = $outputDir.'/preview.jpg';

            // Load image using GD
            $imageInfo = getimagesize($originalPath);
            if (! $imageInfo) {
                return null;
            }

            $mimeType = $imageInfo['mime'];
            $imageWidth = $imageInfo[0];
            $imageHeight = $imageInfo[1];

            $gdImage = match ($mimeType) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($originalPath),
                'image/png' => imagecreatefrompng($originalPath),
                'image/gif' => imagecreatefromgif($originalPath),
                'image/webp' => imagecreatefromwebp($originalPath),
                default => null,
            };

            if (! $gdImage) {
                return null;
            }

            // Watermark text - three lines
            $lines = ['FOR', 'PREVIEW', 'ONLY'];
            $minDimension = min($imageWidth, $imageHeight);
            
            // Find a system TTF font for smooth rendering
            $fontPath = $this->findSystemFont();
            
            // Calculate initial font sizes: PREVIEW is larger and bolder
            $previewFontSize = max((int) ($minDimension * 0.08), 60); // Larger for PREVIEW
            $otherFontSize = max((int) ($minDimension * 0.05), 35); // Smaller for FOR and ONLY
            
            // Calculate actual text widths and scale if needed to fit image width
            $maxAvailableWidth = $imageWidth * 0.9; // 90% of image width for padding
            $maxTextWidth = 0;
            
            if ($fontPath && function_exists('imagettfbbox')) {
                // Get actual text widths using TTF
                $previewBbox = imagettfbbox($previewFontSize, 0, $fontPath, 'PREVIEW');
                $previewTextWidth = abs($previewBbox[4] - $previewBbox[0]);
                $maxTextWidth = max($maxTextWidth, $previewTextWidth);
                
                foreach (['FOR', 'ONLY'] as $line) {
                    $bbox = imagettfbbox($otherFontSize, 0, $fontPath, $line);
                    $textWidth = abs($bbox[4] - $bbox[0]);
                    $maxTextWidth = max($maxTextWidth, $textWidth);
                }
            } else {
                // Estimate using GD font metrics
                $previewTextWidth = strlen('PREVIEW') * ($previewFontSize * 0.6);
                $maxTextWidth = max($previewTextWidth, strlen('FOR') * ($otherFontSize * 0.6), strlen('ONLY') * ($otherFontSize * 0.6));
            }
            
            // Scale down font sizes if text exceeds available width
            if ($maxTextWidth > $maxAvailableWidth) {
                $scaleFactor = $maxAvailableWidth / $maxTextWidth;
                $previewFontSize = (int) ($previewFontSize * $scaleFactor);
                $otherFontSize = (int) ($otherFontSize * $scaleFactor);
            }
            
            // Line height spacing
            $lineSpacing = (int) ($previewFontSize * 0.3);
            $totalHeight = ($otherFontSize * 2) + $previewFontSize + ($lineSpacing * 2);
            
            // Use full image width for watermark background
            $padding = (int) ($previewFontSize * 0.4);
            $textWidth = $imageWidth; // Full width
            $textHeight = (int) ($totalHeight + ($padding * 2));

            // Create watermark canvas with transparency (full width)
            $watermarkCanvas = imagecreatetruecolor($textWidth, $textHeight);
            imagealphablending($watermarkCanvas, false);
            imagesavealpha($watermarkCanvas, true);
            $transparent = imagecolorallocatealpha($watermarkCanvas, 0, 0, 0, 127);
            imagefill($watermarkCanvas, 0, 0, $transparent);

            // Draw semi-transparent dark background
            imagealphablending($watermarkCanvas, true);
            $bgColor = imagecolorallocatealpha($watermarkCanvas, 0, 0, 0, 100); // Darker background
            imagefilledrectangle($watermarkCanvas, 0, 0, $textWidth - 1, $textHeight - 1, $bgColor);
            
            // Draw each line of text
            $yOffset = $padding;
            foreach ($lines as $index => $line) {
                $isPreview = $line === 'PREVIEW';
                $currentFontSize = $isPreview ? $previewFontSize : $otherFontSize;
                
                // Use TTF font if available for smooth rendering
                if ($fontPath && function_exists('imagettftext')) {
                    // Render at higher resolution for smoother text
                    $renderScale = 3;
                    $renderSize = $currentFontSize * $renderScale;
                    
                    // Get text bounding box to calculate dimensions
                    $bbox = imagettfbbox($renderSize, 0, $fontPath, $line);
                    $textWidthActual = abs($bbox[4] - $bbox[0]);
                    $textHeightActual = abs($bbox[7] - $bbox[1]);
                    
                    // Create line canvas at render scale
                    $lineRenderCanvas = imagecreatetruecolor($textWidthActual + 20, $textHeightActual + 20);
                    imagealphablending($lineRenderCanvas, false);
                    imagesavealpha($lineRenderCanvas, true);
                    $transparentLine = imagecolorallocatealpha($lineRenderCanvas, 0, 0, 0, 127);
                    imagefill($lineRenderCanvas, 0, 0, $transparentLine);
                    
                    // Draw text using TTF at render scale
                    imagealphablending($lineRenderCanvas, true);
                    $textColor = imagecolorallocate($lineRenderCanvas, 255, 255, 255);
                    $angle = 0;
                    $x = 10; // Padding
                    $y = 10 + $textHeightActual; // Baseline
                    
                    imagettftext($lineRenderCanvas, $renderSize, $angle, $x, $y, $textColor, $fontPath, $line);
                    
                    // Scale down to final size with smooth interpolation
                    $finalWidth = (int) ($textWidthActual / $renderScale);
                    $finalHeight = (int) ($textHeightActual / $renderScale);
                    $lineCanvas = imagecreatetruecolor($finalWidth, $finalHeight);
                    imagealphablending($lineCanvas, false);
                    imagesavealpha($lineCanvas, true);
                    $transparentFinal = imagecolorallocatealpha($lineCanvas, 0, 0, 0, 127);
                    imagefill($lineCanvas, 0, 0, $transparentFinal);
                    
                    imagealphablending($lineCanvas, true);
                    imagecopyresampled(
                        $lineCanvas, $lineRenderCanvas,
                        0, 0,
                        10, 10,
                        $finalWidth, $finalHeight,
                        $textWidthActual, $textHeightActual
                    );
                    imagedestroy($lineRenderCanvas);
                    
                    // Center the line horizontally
                    $xOffset = (int) (($textWidth - $finalWidth) / 2);
                    
                    // Copy line to watermark canvas
                    imagecopy($watermarkCanvas, $lineCanvas, $xOffset, $yOffset, 0, 0, $finalWidth, $finalHeight);
                    imagedestroy($lineCanvas);
                    
                    // Move to next line
                    $yOffset += $finalHeight + ($isPreview ? $lineSpacing : ($lineSpacing * 0.7));
                } else {
                    // Fallback to GD font with better scaling
                    $lineWidth = (int) (strlen($line) * ($currentFontSize * 0.6));
                    $lineCanvas = imagecreatetruecolor($lineWidth, $currentFontSize);
                    imagealphablending($lineCanvas, false);
                    imagesavealpha($lineCanvas, true);
                    $transparentLine = imagecolorallocatealpha($lineCanvas, 0, 0, 0, 127);
                    imagefill($lineCanvas, 0, 0, $transparentLine);

                    // Render at higher resolution then scale down for smoother result
                    $renderScale = 3;
                    $renderWidth = $lineWidth * $renderScale;
                    $renderHeight = $currentFontSize * $renderScale;
                    $renderCanvas = imagecreatetruecolor($renderWidth, $renderHeight);
                    
                    $gdFontSize = 5;
                    $gdBaseSize = 13;
                    $baseWidth = strlen($line) * 6;
                    $baseCanvas = imagecreatetruecolor($baseWidth, $gdBaseSize);
                    $textColor = imagecolorallocate($baseCanvas, 255, 255, 255);
                    imagestring($baseCanvas, $gdFontSize, 0, 0, $line, $textColor);

                    // Scale up to render size
                    imagecopyresampled($renderCanvas, $baseCanvas, 0, 0, 0, 0, $renderWidth, $renderHeight, $baseWidth, $gdBaseSize);
                    imagedestroy($baseCanvas);

                    // Scale down to final size with smooth interpolation
                    imagealphablending($lineCanvas, true);
                    imagecopyresampled($lineCanvas, $renderCanvas, 0, 0, 0, 0, $lineWidth, $currentFontSize, $renderWidth, $renderHeight);
                    imagedestroy($renderCanvas);

                    // Center the line horizontally
                    $xOffset = (int) (($textWidth - $lineWidth) / 2);
                    
                    // Copy line to watermark canvas
                    imagecopy($watermarkCanvas, $lineCanvas, $xOffset, $yOffset, 0, 0, $lineWidth, $currentFontSize);
                    imagedestroy($lineCanvas);

                    // Move to next line
                    $yOffset += $currentFontSize + ($isPreview ? $lineSpacing : ($lineSpacing * 0.7));
                }
            }

            // Composite watermark onto main image (center position)
            $watermarkX = (int) (($imageWidth - $textWidth) / 2);
            $watermarkY = (int) (($imageHeight - $textHeight) / 2);
            imagealphablending($gdImage, true);
            imagecopy($gdImage, $watermarkCanvas, $watermarkX, $watermarkY, 0, 0, $textWidth, $textHeight);
            imagedestroy($watermarkCanvas);

            // Resize to medium size (1200px max) before saving
            $maxSize = 1200;
            $ratio = min($maxSize / $imageWidth, $maxSize / $imageHeight, 1);
            $newWidth = (int) ($imageWidth * $ratio);
            $newHeight = (int) ($imageHeight * $ratio);

            if ($ratio < 1) {
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $gdImage, 0, 0, 0, 0, $newWidth, $newHeight, $imageWidth, $imageHeight);
                imagedestroy($gdImage);
                $gdImage = $resized;
            }

            // Save as JPEG with same quality as medium variant (75-82)
            $quality = random_int(self::JPEG_QUALITY_MIN, self::JPEG_QUALITY_MAX);
            imagejpeg($gdImage, $outputPath, $quality);
            imagedestroy($gdImage);

            return $outputPath;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to generate preview variant', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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

    /**
     * Find a system TTF font for smooth text rendering (prefers Google Fonts, then stylized/bold fonts)
     */
    protected function findSystemFont(): ?string
    {
        if (! function_exists('imagettftext')) {
            return null;
        }

        // First, try to use bundled Google Font (Bebas Neue - stylized and bold)
        $googleFontPath = resource_path('fonts/BebasNeue-Regular.ttf');
        if (file_exists($googleFontPath) && is_readable($googleFontPath)) {
            return $googleFontPath;
        }

        // Try to find stylized font using fc-list (fontconfig) on Linux/macOS
        if (function_exists('exec') && ! empty(shell_exec('which fc-list 2>/dev/null'))) {
            // Try stylized fonts first (Bold, Black, Heavy, Rounded)
            $stylizedFonts = shell_exec('fc-list : file | grep -iE "bold|black|heavy|rounded|condensed" | head -3 2>/dev/null');
            if ($stylizedFonts) {
                $fonts = explode("\n", trim($stylizedFonts));
                foreach ($fonts as $fontPath) {
                    $fontPath = trim($fontPath);
                    if (file_exists($fontPath) && is_readable($fontPath) && pathinfo($fontPath, PATHINFO_EXTENSION) === 'ttf') {
                        return $fontPath;
                    }
                }
            }
        }

        // Common stylized system font paths (prioritize bold/stylized)
        $fontPaths = [
            // macOS - stylized fonts first
            '/System/Library/Fonts/Supplemental/Arial Rounded MT Bold.ttf',
            '/System/Library/Fonts/Supplemental/DIN Condensed Bold.ttf',
            '/System/Library/Fonts/Supplemental/Trebuchet MS Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Black.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Verdana Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial Bold.ttf',
            '/Library/Fonts/Arial.ttf',
            // Linux - bold fonts
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/arial.ttf',
            '/usr/share/fonts/TTF/arial.ttf',
            // Windows (if running on Windows server)
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/ariblk.ttf',
            'C:/Windows/Fonts/arial.ttf',
        ];

        foreach ($fontPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
