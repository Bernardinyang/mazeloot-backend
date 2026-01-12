<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ImageUploadController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\ProductPreferenceController;
use App\Http\Controllers\V1\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
|
| Version 1 of the API routes. All routes here are prefixed with /api/v1
|
*/

// Products routes (public - no authentication required)
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{slug}', [ProductController::class, 'show']);
});

// Auth routes (public - no authentication required, but rate limited)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // Magic link routes
    Route::post('/magic-link/send', [AuthController::class, 'sendMagicLink'])->middleware('throttle:3,1');
    Route::post('/magic-link/verify', [AuthController::class, 'verifyMagicLink'])->middleware('throttle:10,1');

    // OAuth routes
    Route::get('/oauth/{provider}/redirect', [AuthController::class, 'redirectToProvider'])->middleware('throttle:10,1');
    Route::get('/oauth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
});

// Single upload endpoint - supports Sanctum auth
// Note: API key auth middleware (ApiKeyAuth) can be added to support programmatic access
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::get('/auth/storage', [AuthController::class, 'storage']);
    Route::post('/uploads', [UploadController::class, 'upload'])->middleware('throttle:20,1');
    Route::post('/images/upload', [ImageUploadController::class, 'upload'])->middleware('throttle:20,1');

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // Product Preferences
    Route::prefix('product-preferences')->group(function () {
        Route::get('/', [ProductPreferenceController::class, 'index']);
        Route::post('/', [ProductPreferenceController::class, 'store']);
        Route::get('/domain/check', [ProductPreferenceController::class, 'checkDomain']);
        Route::post('/{productId}/setup', [ProductPreferenceController::class, 'setup']);
    });
});

// Domain routes
// Public routes (loaded first - no authentication required)
require __DIR__.'/../domains/memora/public.php';
// Authenticated routes (require authentication)
require __DIR__.'/../domains/memora/selections.php';
require __DIR__.'/../domains/memora/raw-files.php';
require __DIR__.'/../domains/memora/memora.php';
// Admin routes (require authentication and admin role)
require __DIR__.'/../domains/memora/admin.php';
