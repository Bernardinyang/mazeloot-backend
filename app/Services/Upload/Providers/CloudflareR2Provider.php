<?php

namespace App\Services\Upload\Providers;

use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Upload\DTOs\UploadResult;
use App\Services\Upload\Exceptions\UploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudflareR2Provider implements UploadProviderInterface
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('upload.providers.r2.disk', 'r2');
        $this->validateConfiguration();
    }

    /**
     * Validate R2 configuration
     *
     * @throws UploadException
     */
    protected function validateConfiguration(): void
    {
        // Check config values instead of env() directly
        // This works even when config is cached
        $config = config('upload.providers.r2', []);
        $filesystemConfig = config('filesystems.disks.r2', []);
        
        $required = [
            'key' => 'R2_ACCESS_KEY_ID',
            'secret' => 'R2_SECRET_ACCESS_KEY',
            'bucket' => 'R2_BUCKET',
            'endpoint' => 'R2_ENDPOINT',
        ];

        $missing = [];
        foreach ($required as $configKey => $envKey) {
            $value = $config[$configKey] ?? $filesystemConfig[$configKey] ?? null;
            if (empty($value)) {
                $missing[] = $envKey;
            }
        }

        if (! empty($missing)) {
            throw UploadException::providerError(
                'R2 configuration is incomplete. Missing environment variables: '.implode(', ', $missing)
            );
        }
    }

    public function upload(UploadedFile $file, array $options = []): UploadResult
    {
        $path = $this->generatePath($file, $options['path'] ?? null);
        $storedPath = null;
        $lastError = null;

        try {
            // Temporarily enable throwing to capture actual AWS errors
            $originalThrow = config("filesystems.disks.{$this->disk}.throw", false);
            config(["filesystems.disks.{$this->disk}.throw" => true]);

            try {
                $storedPath = Storage::disk($this->disk)->putFileAs(
                    dirname($path),
                    $file,
                    basename($path),
                    'public'
                );
            } finally {
                // Restore original throw setting
                config(["filesystems.disks.{$this->disk}.throw" => $originalThrow]);
            }
        } catch (\Exception $e) {
            $lastError = $e->getMessage();
            Log::error('R2 upload exception', [
                'error' => $lastError,
                'path' => $path,
                'file' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        if (! $storedPath) {
            $errorMsg = $lastError
                ? "Failed to store file to R2: {$lastError}"
                : 'Failed to store file to R2. Check R2 configuration and permissions.';

            Log::error('R2 upload failed', [
                'path' => $path,
                'file' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'error' => $lastError,
            ]);

            throw UploadException::providerError($errorMsg);
        }

        try {
            $url = Storage::disk($this->disk)->url($storedPath);
        } catch (\Exception $e) {
            throw UploadException::providerError(
                'Failed to get URL from R2: '.$e->getMessage()
            );
        }

        // Get image dimensions if it's an image
        $width = null;
        $height = null;
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        return new UploadResult(
            url: $url,
            provider: 'r2',
            path: $storedPath,
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
            checksum: hash_file('sha256', $file->getRealPath()),
            originalFilename: $file->getClientOriginalName(),
            width: $width,
            height: $height,
        );
    }

    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    public function getSignedUrl(string $path, int $expirationMinutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl($path, now()->addMinutes($expirationMinutes));
    }

    public function getPublicUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    protected function generatePath(UploadedFile $file, ?string $basePath = null): string
    {
        $base = $basePath ?? 'uploads';
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $datePath = date('Y/m/d');

        return "{$base}/{$datePath}/{$filename}";
    }
}
