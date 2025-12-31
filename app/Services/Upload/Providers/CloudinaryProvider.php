<?php

namespace App\Services\Upload\Providers;

use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Upload\DTOs\UploadResult;
use App\Services\Upload\Exceptions\UploadException;
use Illuminate\Http\UploadedFile;

class CloudinaryProvider implements UploadProviderInterface
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('upload.providers.cloudinary', []);

        if (empty($this->config['cloud_name']) || empty($this->config['api_key']) || empty($this->config['api_secret'])) {
            throw UploadException::providerError('Cloudinary configuration is incomplete');
        }
    }

    public function upload(UploadedFile $file, array $options = []): UploadResult
    {
        // Cloudinary SDK would be used here
        // For now, this is a placeholder that shows the interface
        // In production, install cloudinary/cloudinary_php package

        throw UploadException::providerError('Cloudinary provider not yet implemented. Install cloudinary/cloudinary_php package.');
    }

    public function delete(string $path): bool
    {
        // Cloudinary delete implementation
        throw UploadException::providerError('Cloudinary delete not yet implemented');
    }

    public function getSignedUrl(string $path, int $expirationMinutes = 60): string
    {
        // Cloudinary signed URL implementation
        throw UploadException::providerError('Cloudinary signed URL not yet implemented');
    }

    public function getPublicUrl(string $path): string
    {
        $cloudName = $this->config['cloud_name'];

        return "https://res.cloudinary.com/{$cloudName}/{$path}";
    }
}
