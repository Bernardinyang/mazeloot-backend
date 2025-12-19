<?php

namespace App\Services\Upload\Providers;

use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Upload\DTOs\UploadResult;
use App\Services\Upload\Exceptions\UploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudflareR2Provider implements UploadProviderInterface
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('upload.providers.r2.disk', 'r2');
    }

    public function upload(UploadedFile $file, array $options = []): UploadResult
    {
        // R2 is S3-compatible, so we can use S3 driver
        $path = $this->generatePath($file, $options['path'] ?? null);
        $storedPath = Storage::disk($this->disk)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            'public'
        );

        if (!$storedPath) {
            throw UploadException::providerError('Failed to store file to R2');
        }

        $url = Storage::disk($this->disk)->url($storedPath);

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
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $datePath = date('Y/m/d');

        return "{$base}/{$datePath}/{$filename}";
    }
}
