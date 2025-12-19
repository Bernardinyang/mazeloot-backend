<?php

namespace App\Services\Upload\Providers;

use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Upload\DTOs\UploadResult;
use App\Services\Upload\Exceptions\UploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalProvider implements UploadProviderInterface
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('upload.providers.local.disk', 'local');
    }

    public function upload(UploadedFile $file, array $options = []): UploadResult
    {
        $path = $this->generatePath($file, $options['path'] ?? null);
        $storedPath = Storage::disk($this->disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if (!$storedPath) {
            throw UploadException::providerError('Failed to store file');
        }

        $fullPath = Storage::disk($this->disk)->path($storedPath);
        $url = Storage::disk($this->disk)->url($storedPath);

        // Get image dimensions if it's an image
        $width = null;
        $height = null;
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        return new UploadResult(
            url: $url,
            provider: 'local',
            path: $storedPath,
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
            checksum: hash_file('sha256', $fullPath),
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
        // Local storage doesn't need signed URLs, return public URL
        return Storage::disk($this->disk)->url($path);
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
