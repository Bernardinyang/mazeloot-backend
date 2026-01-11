<?php

namespace App\Services\Image;

use App\Services\Upload\Contracts\UploadProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    protected ImageVariantGenerator $variantGenerator;

    protected UploadProviderInterface $uploadProvider;

    protected string $disk;

    protected string $baseUrl;

    public function __construct(ImageVariantGenerator $variantGenerator, UploadProviderInterface $uploadProvider)
    {
        $this->variantGenerator = $variantGenerator;
        $this->uploadProvider = $uploadProvider;
        $this->initializeStorage();
    }

    /**
     * Initialize storage disk and base URL based on configured provider
     */
    protected function initializeStorage(): void
    {
        $provider = config('upload.default_provider', 'local');

        $this->disk = match ($provider) {
            'local' => config('upload.providers.local.disk', 'public'),
            's3' => config('upload.providers.s3.disk', 's3'),
            'r2' => config('upload.providers.r2.disk', 'r2'),
            'cloudinary' => 'cloudinary', // Cloudinary doesn't use disk
            default => 'public',
        };

        // Get base URL based on provider
        $this->baseUrl = $this->getBaseUrlForProvider($provider);
    }

    /**
     * Get base URL for the configured provider
     */
    protected function getBaseUrlForProvider(string $provider): string
    {
        return match ($provider) {
            'local' => rtrim(config('app.url', 'http://localhost'), '/'),
            's3' => rtrim(config('filesystems.disks.s3.url', config('upload.providers.s3.url', '')), '/'),
            'r2' => rtrim(config('filesystems.disks.r2.url', config('upload.providers.r2.url', env('R2_URL', ''))), '/'),
            'cloudinary' => rtrim(config('upload.providers.cloudinary.url', ''), '/'),
            default => rtrim(config('app.url', 'http://localhost'), '/'),
        };
    }

    /**
     * Upload single image with variants
     *
     * @return array{uuid: string, variants: array<string, string>, meta: array{width: int, height: int, size: int}}
     */
    public function uploadImage(UploadedFile $file, array $options = []): array
    {
        $uuid = (string) Str::uuid();
        $tempPath = $file->getRealPath();
        $dimensions = $this->variantGenerator->getDimensions($tempPath);
        $provider = config('upload.default_provider', 'local');

        // Get original file extension from the uploaded file
        $originalExtension = $file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        if (empty($originalExtension)) {
            // Fallback: try to detect from MIME type
            $mimeType = $file->getMimeType();
            $originalExtension = match (true) {
                str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') => 'jpg',
                str_contains($mimeType, 'png') => 'png',
                str_contains($mimeType, 'webp') => 'webp',
                str_contains($mimeType, 'gif') => 'gif',
                str_contains($mimeType, 'svg') => 'svg',
                default => 'jpg',
            };
        }

        // Check if file is SVG - SVG files don't need variant generation
        $isSvg = strtolower($originalExtension) === 'svg' || str_contains($file->getMimeType(), 'svg');

        // Create temporary directory for variants
        $tempDir = sys_get_temp_dir().'/'.$uuid;
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // For SVG files, skip variant generation and just copy the original
            if ($isSvg) {
                $originalVariant = $tempDir.'/original.'.strtolower($originalExtension);
                copy($tempPath, $originalVariant);
                $variantPaths = ['original' => $originalVariant];
                $dimensions = ['width' => 0, 'height' => 0]; // SVG dimensions are viewBox-based
            } else {
                // Generate all variants (pass original extension for proper handling)
                $variantPaths = $this->variantGenerator->generateVariants($tempPath, $tempDir, $originalExtension);
            }

            // Upload all variants to storage
            $uploadedVariants = [];
            $basePath = 'uploads/images/'.$uuid;

            // Cloudinary doesn't use Storage facade - use provider's upload method
            $variantSizes = []; // Track sizes of all variants
            if ($provider === 'cloudinary') {
                foreach ($variantPaths as $variantName => $localPath) {
                    $extension = pathinfo($localPath, PATHINFO_EXTENSION);
                    $variantPath = $basePath.'/'.$variantName.'.'.$extension;

                    // Get variant size from local temp file
                    $variantSize = file_exists($localPath) ? filesize($localPath) : 0;
                    $variantSizes[$variantName] = $variantSize;

                    // Create UploadedFile instance from variant file
                    $variantFile = $this->createUploadedFileFromPath($localPath, $variantName.'.'.$extension);

                    // Use provider's upload method
                    $uploadResult = $this->uploadProvider->upload($variantFile, [
                        'path' => dirname($variantPath),
                    ]);

                    $uploadedVariants[$variantName] = $uploadResult->url;
                }
                // Calculate total size including all variants
                $totalSizeWithVariants = array_sum($variantSizes);
            } else {
                // For Storage-based providers (local, s3, r2), use Storage facade
                $variantSizes = []; // Track sizes of all variants
                foreach ($variantPaths as $variantName => $localPath) {
                    // Get extension from the local file path
                    $extension = pathinfo($localPath, PATHINFO_EXTENSION);

                    // For original variant, ensure we use the original extension
                    if ($variantName === 'original' && ! empty($originalExtension)) {
                        $extension = $originalExtension;
                    }

                    // Fallback if extension is still empty
                    if (empty($extension)) {
                        $extension = 'jpg'; // Default to jpg
                    }

                    $storagePath = $basePath.'/'.$variantName.'.'.strtolower($extension);

                    // Get variant size before uploading (from local temp file)
                    $variantSize = file_exists($localPath) ? filesize($localPath) : 0;
                    $variantSizes[$variantName] = $variantSize;

                    // Upload to storage using Storage facade
                    $contents = file_get_contents($localPath);
                    $uploaded = Storage::disk($this->disk)->put($storagePath, $contents, 'public');

                    if (! $uploaded) {
                        Log::error("Failed to upload variant {$variantName} to {$this->disk} at path: {$storagePath}");
                        throw new \RuntimeException("Failed to upload variant {$variantName} to storage");
                    }

                    Log::info("Uploaded variant {$variantName} to {$this->disk}: {$storagePath}");

                    // Build public URL using provider's method or Storage URL
                    $uploadedVariants[$variantName] = $this->getPublicUrl($storagePath);
                }

                // Calculate total size including all variants
                $totalSizeWithVariants = array_sum($variantSizes);
            }

            return [
                'uuid' => $uuid,
                'variants' => $uploadedVariants,
                'variant_sizes' => $variantSizes ?? [],
                'meta' => [
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'size' => $file->getSize(),
                    'total_size_with_variants' => $totalSizeWithVariants ?? $file->getSize(),
                ],
            ];
        } finally {
            // Cleanup temporary directory
            $this->cleanupTempDir($tempDir);
        }
    }

    /**
     * Upload multiple images
     *
     * @param  array<UploadedFile>  $files
     * @return array<array{uuid: string, variants: array<string, string>, meta: array{width: int, height: int, size: int}}>
     */
    public function uploadMultipleImages(array $files, array $options = []): array
    {
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->uploadImage($file, $options);
        }

        return $results;
    }

    /**
     * Get public URL for a storage path
     */
    protected function getPublicUrl(string $path): string
    {
        $provider = config('upload.default_provider', 'local');

        // Use provider's getPublicUrl method if available
        if (method_exists($this->uploadProvider, 'getPublicUrl')) {
            try {
                $url = $this->uploadProvider->getPublicUrl($path);
                // Ensure absolute URL for local storage
                if ($provider === 'local' && ! filter_var($url, FILTER_VALIDATE_URL)) {
                    $url = $this->baseUrl.'/'.ltrim($url, '/');
                }

                return $url;
            } catch (\Exception $e) {
                // Fallback to Storage URL if provider method fails
                Log::warning('Failed to get public URL from provider: '.$e->getMessage());
            }
        }

        // Fallback to Storage facade URL
        try {
            $url = Storage::disk($this->disk)->url($path);

            // Ensure absolute URL for local storage
            if ($provider === 'local' && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $url = $this->baseUrl.'/'.ltrim($url, '/');
            }

            return $url;
        } catch (\Exception $e) {
            // Last resort: construct URL manually
            Log::warning('Failed to get URL from Storage disk: '.$e->getMessage());

            return $this->baseUrl.'/'.$path;
        }
    }

    /**
     * Create UploadedFile instance from a file path
     * Used for providers that require UploadedFile (e.g., Cloudinary)
     */
    protected function createUploadedFileFromPath(string $filePath, string $originalName): UploadedFile
    {
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        return new UploadedFile(
            $filePath,
            $originalName,
            $mimeType,
            null,
            true // test mode
        );
    }

    /**
     * Cleanup temporary directory
     */
    protected function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir.'/'.$file;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        @rmdir($dir);
    }
}
