<?php

namespace App\Support\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a successful JSON response matching the frontend contract
     */
    public static function success(mixed $data = null, int $status = 200, string $statusText = 'OK'): JsonResponse
    {
        // For 204 No Content, return empty response body but still with status in body if needed
        if ($status === 204) {
            return response()->json([
                'data' => $data,
                'status' => $status,
                'statusText' => 'No Content',
            ], 200); // Return 200 with status in body for consistency
        }

        return response()->json([
            'data' => $data,
            'status' => $status,
            'statusText' => $statusText,
        ], $status);
    }

    /**
     * Return an error JSON response matching the frontend contract
     * Returns only a single error message (no arrays)
     */
    public static function error(
        string $message,
        ?string $code = null,
        int $status = 400,
        ?array $metadata = null
    ): JsonResponse {
        $response = [
            'message' => $message,
            'status' => $status,
        ];

        if ($code) {
            $response['code'] = $code;
        }

        if ($metadata) {
            $response['metadata'] = $metadata;
        }

        return response()->json($response, $status);
    }

    // ==================== Success Methods ====================

    /**
     * Return a 200 OK success response
     */
    public static function successOk(mixed $data = null): JsonResponse
    {
        return self::success($data, 200, 'OK');
    }

    /**
     * Return a 201 Created success response
     */
    public static function successCreated(mixed $data = null): JsonResponse
    {
        return self::success($data, 201, 'Created');
    }

    /**
     * Return a 202 Accepted success response
     */
    public static function successAccepted(mixed $data = null): JsonResponse
    {
        return self::success($data, 202, 'Accepted');
    }

    /**
     * Return a 204 No Content success response
     */
    public static function successNoContent(): JsonResponse
    {
        return self::success(null, 204, 'No Content');
    }

    // ==================== Error Methods ====================

    /**
     * Return a 400 Bad Request error response
     */
    public static function errorBadRequest(string $message, ?string $code = 'BAD_REQUEST'): JsonResponse
    {
        return self::error($message, $code, 400);
    }

    /**
     * Return a 401 Unauthorized error response
     */
    public static function errorUnauthorized(string $message = 'Unauthorized', ?string $code = 'UNAUTHORIZED'): JsonResponse
    {
        return self::error($message, $code, 401);
    }

    /**
     * Return a 403 Forbidden error response
     */
    public static function errorForbidden(string $message = 'Forbidden', ?string $code = 'FORBIDDEN'): JsonResponse
    {
        return self::error($message, $code, 403);
    }

    /**
     * Return a 404 Not Found error response
     */
    public static function errorNotFound(string $message = 'Resource not found', ?string $code = 'NOT_FOUND'): JsonResponse
    {
        return self::error($message, $code, 404);
    }

    /**
     * Return a 409 Conflict error response
     */
    public static function errorConflict(string $message, ?string $code = 'CONFLICT'): JsonResponse
    {
        return self::error($message, $code, 409);
    }

    /**
     * Return a 422 Unprocessable Entity (Validation) error response
     * If an errors array is provided, extracts the first error message
     *
     * @param  array|null  $errors  Optional: if provided, will extract first error from array
     */
    public static function errorValidation(string $message = 'Validation failed', ?array $errors = null, ?string $code = 'VALIDATION_ERROR'): JsonResponse
    {
        // If errors array is provided, extract the first error message
        if ($errors && ! empty($errors)) {
            $firstFieldErrors = reset($errors);
            $firstError = is_array($firstFieldErrors) ? reset($firstFieldErrors) : $firstFieldErrors;
            if ($firstError) {
                $message = $firstError;
            }
        }

        return self::error($message, $code, 422);
    }

    /**
     * Return a 429 Too Many Requests error response
     */
    public static function errorTooManyRequests(string $message = 'Too many requests', ?string $code = 'TOO_MANY_REQUESTS'): JsonResponse
    {
        return self::error($message, $code, 429);
    }

    /**
     * Return a 500 Internal Server Error response
     */
    public static function errorInternalServerError(string $message = 'Internal server error', ?string $code = 'INTERNAL_SERVER_ERROR'): JsonResponse
    {
        return self::error($message, $code, 500);
    }

    /**
     * Return a 503 Service Unavailable error response
     */
    public static function errorServiceUnavailable(string $message = 'Service unavailable', ?string $code = 'SERVICE_UNAVAILABLE'): JsonResponse
    {
        return self::error($message, $code, 503);
    }
}
