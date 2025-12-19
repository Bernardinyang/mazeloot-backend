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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API routes always return JSON responses for validation errors
        // Return only the first error message instead of an array
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $errors = $e->errors();
                // Get the first error message from the first field
                $firstError = null;
                if (!empty($errors)) {
                    $firstFieldErrors = reset($errors);
                    $firstError = is_array($firstFieldErrors) ? reset($firstFieldErrors) : $firstFieldErrors;
                }
                
                return response()->json([
                    'message' => $firstError ?: 'The given data was invalid.',
                    'status' => 422,
                    'code' => 'VALIDATION_ERROR',
                ], 422);
            }
        });
    })->create();
