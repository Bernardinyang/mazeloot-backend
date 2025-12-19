<?php

namespace App\Services\Upload\Exceptions;

use App\Support\Exceptions\ApiException;

class UploadException extends ApiException
{
    public static function providerError(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            $message,
            'UPLOAD_PROVIDER_ERROR',
            500,
            null,
            $previous
        );
    }

    public static function quotaExceeded(string $message = 'Upload quota exceeded'): self
    {
        return new self(
            $message,
            'UPLOAD_QUOTA_EXCEEDED',
            413
        );
    }

    public static function invalidFile(string $message = 'Invalid file'): self
    {
        return new self(
            $message,
            'INVALID_FILE',
            400
        );
    }
}
