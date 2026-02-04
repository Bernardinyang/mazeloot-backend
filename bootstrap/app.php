<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api-key' => \App\Http\Middleware\ApiKeyAuth::class,
            'guest.token' => \App\Http\Middleware\GuestTokenAuth::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'superadmin' => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
            'memora.feature' => \App\Http\Middleware\MemoraFeature::class,
            'memora.not_starter' => \App\Http\Middleware\EnsureNotStarterPlan::class,
            'paystack.webhook.body' => \App\Http\Middleware\CapturePaystackWebhookBody::class,
            'flutterwave.webhook.body' => \App\Http\Middleware\CaptureFlutterwaveWebhookBody::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
            'api/v1/webhooks/stripe',
            'api/v1/webhooks/paypal',
            'api/v1/webhooks/paystack',
            'api/v1/webhooks/flutterwave',
        ]);

        // Ensure CORS middleware handles OPTIONS requests
        // Apply globally to handle both API and broadcasting routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\CacheSanctumToken::class,
        ]);

        $middleware->web(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle database connection limit errors (MySQL error 1203)
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            // Check if it's MySQL error 1203 (connection limit)
            if ($errorCode === '42000' && (
                str_contains($errorMessage, '1203') ||
                str_contains($errorMessage, 'max_user_connections') ||
                str_contains($errorMessage, 'User already has more than')
            )) {
                \Illuminate\Support\Facades\Log::error('Database connection limit reached', [
                    'error' => $errorMessage,
                    'url' => $request->fullUrl(),
                ]);

                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'message' => 'Service temporarily unavailable. Please try again in a moment.',
                        'status' => 503,
                        'code' => 'SERVICE_UNAVAILABLE',
                    ], 503)->header('Retry-After', '5');
                }

                return response('Service temporarily unavailable. Please try again in a moment.', 503)
                    ->header('Retry-After', '5');
            }
        });

        // Ensure API routes always return JSON responses for validation errors
        // Return only the first error message instead of an array
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $errors = $e->errors();

                // Check if file upload failed due to PHP limits (file not received)
                $isFileUpload = $request->has('file') || $request->has('files');
                $hasActualFile = $request->hasFile('file') || $request->hasFile('files');

                if ($isFileUpload && ! $hasActualFile) {
                    // File wasn't uploaded - likely PHP upload limits
                    $phpMaxSize = ini_get('upload_max_filesize');
                    $phpPostMaxSize = ini_get('post_max_size');

                    return response()->json([
                        'message' => "File upload failed. File may be too large. PHP limits: upload_max_filesize={$phpMaxSize}, post_max_size={$phpPostMaxSize}. Please reduce file size or increase PHP limits.",
                        'status' => 422,
                        'code' => 'UPLOAD_SIZE_EXCEEDED',
                    ], 422);
                }

                // Log validation errors for debugging
                \Illuminate\Support\Facades\Log::warning('Validation failed', [
                    'errors' => $errors,
                    'file' => $request->hasFile('file') ? [
                        'name' => $request->file('file')->getClientOriginalName(),
                        'size' => $request->file('file')->getSize(),
                        'mime' => $request->file('file')->getMimeType(),
                    ] : null,
                ]);

                // Get the first error message from the first field
                $firstError = null;
                if (! empty($errors)) {
                    $firstFieldErrors = reset($errors);
                    $firstError = is_array($firstFieldErrors) ? reset($firstFieldErrors) : $firstFieldErrors;
                }

                // Provide a more specific fallback message based on the request
                $fallbackMessage = 'Validation failed. Please check your input.';
                if ($request->hasFile('file') || $request->hasFile('files')) {
                    $fallbackMessage = 'File upload validation failed. Please check file size and type.';
                }

                return response()->json([
                    'message' => $firstError ?: $fallbackMessage,
                    'status' => 422,
                    'code' => 'VALIDATION_ERROR',
                ], 422);
            }
        });
    })->create();
