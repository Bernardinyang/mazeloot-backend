<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
| API versioning is handled through route prefixes. Each version should
| be included here with its corresponding prefix.
|
*/

// Broadcasting authentication route with Sanctum
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    try {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }
        
        $channelName = $request->input('channel_name');
        
        // Manual channel authorization for private-user channels
        if (preg_match('/^private-user\.(.+)$/', $channelName, $matches)) {
            $userId = $matches[1];
            if ((string) $user->uuid === (string) $userId) {
                $broadcaster = \Illuminate\Support\Facades\Broadcast::driver();
                return $broadcaster->validAuthenticationResponse($request, true);
            }
        }
        
        return Broadcast::auth($request);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Broadcasting auth error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['message' => 'Broadcasting authentication failed'], 500);
    }
})->middleware('auth:sanctum');

// Health check endpoint (no version prefix)
Route::get('/health', [\App\Http\Controllers\V1\HealthController::class, 'check']);

// API Version 1
Route::prefix('v1')->group(function () {
    require __DIR__.'/api/v1.php';
});

// Fallback route for unmatched API requests
Route::fallback(static function () {
    return response()->json([
        'message' => 'API endpoint not found',
        'code' => 'NOT_FOUND',
        'status' => 404,
    ], 404);
});
