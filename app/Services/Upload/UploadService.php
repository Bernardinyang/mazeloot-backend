<?php

namespace App\Services\Upload;

use App\Services\Quotas\QuotaService;
use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Upload\DTOs\UploadResult;
use App\Services\Upload\Exceptions\UploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class UploadService
{
    protected UploadProviderInterface $provider;

    protected QuotaService $quotaService;

    public function __construct(UploadProviderInterface $provider, QuotaService $quotaService)
    {
        $this->provider = $provider;
        $this->quotaService = $quotaService;
    }

    /**
     * Upload multiple files
     *
     * @param  array<UploadedFile>  $files
     * @return array<UploadResult>
     *
     * @throws UploadException
     */
    public function uploadMultiple(array $files, array $options = []): array
    {
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->upload($file, $options);
        }

        return $results;
    }

    /**
     * Upload a single file
     *
     * @param  array  $options  Optional: purpose, path, domain, userId
     *
     * @throws UploadException
     */
    public function upload(UploadedFile $file, array $options = []): UploadResult
    {
        // Validate file
        $this->validateFile($file, $options);

        // Check quota if domain/user specified
        if (isset($options['domain']) || isset($options['userId'])) {
            $this->quotaService->checkUploadQuota(
                $file->getSize(),
                $options['domain'] ?? null,
                $options['userId'] ?? null
            );
        }

        // Upload via provider
        return $this->provider->upload($file, $options);
    }

    /**
     * Validate file according to options
     *
     * @throws UploadException
     */
    protected function validateFile(UploadedFile $file, array $options): void
    {
        // Check file size limit
        $maxSize = $options['maxSize'] ?? config('upload.max_size', 262144000); // 250MB default
        if ($file->getSize() > $maxSize) {
            throw UploadException::invalidFile(
                'File size exceeds maximum allowed size of '.number_format($maxSize / 1024 / 1024, 2).' MB'
            );
        }

        // Check allowed file types
        if (isset($options['allowedTypes']) && is_array($options['allowedTypes'])) {
            $mimeType = $file->getMimeType();
            if (! in_array($mimeType, $options['allowedTypes'], true)) {
                throw UploadException::invalidFile(
                    "File type {$mimeType} is not allowed. Allowed types: ".implode(', ', $options['allowedTypes'])
                );
            }
        }
    }

    /**
     * Delete multiple files (called by DeleteFileJob).
     *
     * @param  string  $filePath  Main file path
     * @param  array|null  $additionalPaths  Additional related file paths (thumbnails, low-res copies, etc.)
     *
     * @throws \Exception
     */
    public function deleteFiles(string $filePath, ?array $additionalPaths = null): void
    {
        try {
            // Delete the main file
            $deleted = $this->delete($filePath);

            if (! $deleted) {
                Log::warning("Failed to delete file: {$filePath}");
                // Don't throw exception, just log - file might already be deleted
            } else {
                Log::info("File deleted successfully: {$filePath}");
            }

            // Delete additional related files (thumbnails, low-res copies, etc.)
            if ($additionalPaths) {
                foreach ($additionalPaths as $path) {
                    try {
                        $this->delete($path);
                    } catch (\Exception $e) {
                        Log::warning("Failed to delete additional file {$path}: ".$e->getMessage());
                        // Continue with other files even if one fails
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error deleting file {$filePath}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a file
     */
    public function delete(string $path): bool
    {
        return $this->provider->delete($path);
    }

    /**
     * Get signed URL for a file
     */
    public function getSignedUrl(string $path, int $expirationMinutes = 60): string
    {
        return $this->provider->getSignedUrl($path, $expirationMinutes);
    }
}
