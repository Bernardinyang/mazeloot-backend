<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\CacheController;
use App\Http\Controllers\V1\EarlyAccessController;
use App\Http\Controllers\V1\ImageUploadController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\OnboardingController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\ProductSelectionController;
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

// Cache management routes (public but protected by secret token)
Route::prefix('cache')->group(function () {
    Route::get('/clear-all', [CacheController::class, 'clearAll']);
});

// Products (public or authenticated)
Route::get('/products', [ProductController::class, 'index']);

// Token verification (public, but requires valid token)
Route::get('/onboarding/verify-token', [OnboardingController::class, 'verifyToken']);

// Auth routes (public - no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Magic link routes
    Route::post('/magic-link/send', [AuthController::class, 'sendMagicLink']);
    Route::post('/magic-link/verify', [AuthController::class, 'verifyMagicLink']);

    // OAuth routes
    Route::get('/oauth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
    Route::get('/oauth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
});

// Single upload endpoint - supports Sanctum auth
// Note: API key auth middleware (ApiKeyAuth) can be added to support programmatic access
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::get('/auth/storage', [AuthController::class, 'storage']);
    Route::post('/uploads', [UploadController::class, 'upload']);
    Route::post('/images/upload', [ImageUploadController::class, 'upload']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // Product selection (must be before /products/{slug} to avoid route conflict)
    Route::prefix('products')->group(function () {
        Route::get('/selected', [ProductSelectionController::class, 'index']);
        Route::post('/select', [ProductSelectionController::class, 'store']);
    });

    // Onboarding
    Route::prefix('onboarding')->group(function () {
        Route::post('/token', [OnboardingController::class, 'generateToken']);
        Route::post('/product-selection-token', [OnboardingController::class, 'generateProductSelectionToken']);
        Route::get('/status', [OnboardingController::class, 'getStatus']);
        Route::post('/step', [OnboardingController::class, 'completeStep']);
        Route::post('/complete', [OnboardingController::class, 'complete']);
        
        // Memora-specific onboarding helpers
        Route::post('/memora/validate-domain', [OnboardingController::class, 'validateDomain']);
    });

    // Early Access
    Route::prefix('early-access')->group(function () {
        Route::post('/request', [EarlyAccessController::class, 'requestEarlyAccess'])->middleware('throttle:3,1440');
        Route::get('/request/status', [EarlyAccessController::class, 'getRequestStatus']);
        Route::get('/features', [EarlyAccessController::class, 'getAvailableFeatures']);
        Route::get('/features/{feature}', [EarlyAccessController::class, 'checkFeature']);
    });
});

// Products by slug (must be after /products/selected to avoid route conflict)
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Domain routes
// Public routes (loaded first - no authentication required)
require __DIR__.'/../domains/memora/public.php';
// Authenticated routes (require authentication)
require __DIR__.'/../domains/memora/selections.php';
require __DIR__.'/../domains/memora/raw-files.php';
require __DIR__.'/../domains/memora/memora.php';
// Admin routes (require authentication and admin role)
require __DIR__.'/../domains/memora/admin.php';
require __DIR__.'/v1/admin.php';