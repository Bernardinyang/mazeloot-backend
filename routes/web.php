<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return 'Welcome MAZELOOT';
});

// Broadcasting authentication route with Sanctum
Route::post('/broadcasting/auth', [\App\Http\Controllers\BroadcastController::class, 'authenticate'])
    ->middleware('auth:sanctum');

// Fallback route for unmatched web requests
Route::fallback(static function () {
    return response('Not Found', 404);
});
