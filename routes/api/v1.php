<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ImageUploadController;
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
    Route::post('/uploads', [UploadController::class, 'upload']);
    Route::post('/images/upload', [ImageUploadController::class, 'upload']);
});

// Domain routes
require __DIR__ . '/../domains/memora/selections.php';
require __DIR__ . '/../domains/memora/memora.php';

