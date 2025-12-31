<?php

namespace App\Services\Upload\Contracts;

use App\Services\Upload\DTOs\UploadResult;
use Illuminate\Http\UploadedFile;

interface UploadProviderInterface
{
    /**
     * Upload a file to the storage provider
     *
     * @param  array  $options  Optional: purpose, path, transformations
     */
    public function upload(UploadedFile $file, array $options = []): UploadResult;

    /**
     * Delete a file from the storage provider
     *
     * @param  string  $path  Storage path/identifier
     */
    public function delete(string $path): bool;

    /**
     * Generate a signed URL for temporary access
     *
     * @param  string  $path  Storage path/identifier
     * @param  int  $expirationMinutes  URL expiration time in minutes
     * @return string Signed URL
     */
    public function getSignedUrl(string $path, int $expirationMinutes = 60): string;

    /**
     * Get the public URL for a file
     *
     * @param  string  $path  Storage path/identifier
     * @return string Public URL
     */
    public function getPublicUrl(string $path): string;
}
