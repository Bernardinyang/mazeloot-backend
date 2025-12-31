<?php

namespace App\Services\Upload\DTOs;

readonly class UploadResult
{
    public function __construct(
        public string $url,
        public string $provider,
        public string $path,
        public string $mimeType,
        public int $size,
        public string $checksum,
        public string $originalFilename,
        public ?string $signedUrl = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        $data = [
            'path' => $this->path, // Relative path (primary)
            'url' => $this->signedUrl ?? $this->url, // Full URL for immediate use
            'provider' => $this->provider,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'originalFilename' => $this->originalFilename,
        ];

        if ($this->width !== null && $this->height !== null) {
            $data['width'] = $this->width;
            $data['height'] = $this->height;
        }

        return $data;
    }
}
