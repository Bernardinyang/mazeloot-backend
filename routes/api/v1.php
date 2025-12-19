<?php

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

// Single upload endpoint - supports Sanctum auth
// Note: API key auth middleware (ApiKeyAuth) can be added to support programmatic access
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/uploads', [UploadController::class, 'upload']);
});

// Domain routes
require __DIR__ . '/../domains/memora.php';

