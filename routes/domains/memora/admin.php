<?php

use App\Domains\Memora\Controllers\V1\AdminNotificationController;
use App\Http\Controllers\V1\SocialMediaPlatformController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Memora Admin Routes
|--------------------------------------------------------------------------
|
| Admin-only routes for managing Memora settings
|
*/

Route::middleware(['auth:sanctum'])->prefix('memora/admin')->group(function () {
    // Social Media Platforms (admin only)
    Route::prefix('social-media-platforms')->group(function () {
        Route::get('/', [SocialMediaPlatformController::class, 'index']);
        Route::post('/', [SocialMediaPlatformController::class, 'store']);
        Route::patch('/{id}', [SocialMediaPlatformController::class, 'update']);
        Route::delete('/{id}', [SocialMediaPlatformController::class, 'destroy']);
        Route::patch('/{id}/toggle', [SocialMediaPlatformController::class, 'toggle']);
    });

    // Notification Types (admin only)
    Route::prefix('notifications')->group(function () {
        Route::get('/types', [AdminNotificationController::class, 'getTypes']);
    });
});
