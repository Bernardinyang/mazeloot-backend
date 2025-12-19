<?php

namespace App\Support\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Responses\ApiResponse;

class ApiException extends Exception
{
    protected ?string $errorCode = null;

    public function __construct(
        string $message = '',
        ?string $errorCode = null,
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Render the exception as an HTTP response
     */
    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            $this->getMessage(),
            $this->getErrorCode(),
            $this->getCode() ?: 400
        );
    }
}
