<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return 'Welcome MAZELOOT';
});

// Fallback route for unmatched web requests
Route::fallback(static function () {
    return response('Not Found', 404);
});
