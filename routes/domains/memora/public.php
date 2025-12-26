<?php

use App\Domains\Memora\Controllers\V1\GuestSelectionController;
use App\Domains\Memora\Controllers\V1\PublicMediaController;
use App\Domains\Memora\Controllers\V1\PublicMediaSetController;
use App\Domains\Memora\Controllers\V1\PublicSelectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Selection Routes
|--------------------------------------------------------------------------
|
| Routes for public/guest access to selections.
| These endpoints are separate from authenticated user endpoints.
| 
| Note: While these are "public" routes (no user authentication required),
| they are still protected by guest token middleware. Users must generate
| a guest token via the /token endpoint before accessing these routes.
|
*/

// Public Selection Routes (protected by guest token, not user authentication)
Route::prefix('public/selections')->group(function () {
    // Check selection status (truly public - no authentication required)
    Route::get('/{id}/status', [PublicSelectionController::class, 'checkStatus']);
    
    // Verify password (truly public - no authentication required)
    Route::post('/{id}/verify-password', [PublicSelectionController::class, 'verifyPassword']);
    
    // Generate guest token (truly public - no authentication required)
    Route::post('/{id}/token', [GuestSelectionController::class, 'generateToken']);

    // Guest Selection Routes (protected by guest token middleware)
    Route::middleware(['guest.token'])->group(function () {
        Route::get('/{id}', [PublicSelectionController::class, 'show']);
        Route::get('/{id}/sets', [PublicMediaSetController::class, 'index']);
        Route::get('/{id}/sets/{setId}', [PublicMediaSetController::class, 'show']);
        Route::get('/{id}/sets/{setId}/media', [PublicMediaController::class, 'getSetMedia']);
        Route::get('/{id}/filenames', [PublicSelectionController::class, 'getSelectedFilenames']);
        Route::post('/{id}/complete', [PublicSelectionController::class, 'complete']);
        Route::patch('/{id}/media/{mediaId}/toggle-selected', [PublicMediaController::class, 'toggleSelected']);
    });
});

