<?php

namespace App\Services\Upload\DTOs;

class UploadResult
{
    public function __construct(
        public readonly string $url,
        public readonly string $provider,
        public readonly string $path,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $checksum,
        public readonly string $originalFilename,
        public readonly ?string $signedUrl = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {}

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        $data = [
            'url' => $this->signedUrl ?? $this->url,
            'provider' => $this->provider,
            'path' => $this->path,
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
