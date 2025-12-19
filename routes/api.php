<?php

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

// API Version 1
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1.php';
});

// Fallback route for unmatched API requests
Route::fallback(static function () {
    return response()->json([
        'message' => 'API endpoint not found',
        'code' => 'NOT_FOUND',
        'status' => 404,
    ], 404);
});
