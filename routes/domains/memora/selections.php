<?php

use App\Domains\Memora\Controllers\V1\GuestSelectionController;
use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\MediaSetController;
use App\Domains\Memora\Controllers\V1\SelectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Selection Routes
|--------------------------------------------------------------------------
|
| Routes for managing selections, sets, and media
|
*/

// Authenticated Selection Routes
Route::middleware(['auth:sanctum'])->prefix('selections')->group(function () {
    // Selection CRUD
    Route::get('/', [SelectionController::class, 'index']);
    Route::post('/', [SelectionController::class, 'store']);
    Route::get('/{id}', [SelectionController::class, 'show']);
    Route::patch('/{id}', [SelectionController::class, 'update']);
    Route::delete('/{id}', [SelectionController::class, 'destroy']);
    Route::post('/{id}/publish', [SelectionController::class, 'publish']);
    Route::post('/{id}/recover', [SelectionController::class, 'recover']);
    Route::post('/{id}/star', [SelectionController::class, 'toggleStar']);
    Route::get('/{id}/selected', [SelectionController::class, 'getSelectedMedia']);
    Route::get('/{id}/filenames', [SelectionController::class, 'getSelectedFilenames']);

    // Media Sets within a selection
    Route::prefix('{selectionId}/sets')->group(function () {
        Route::get('/', [MediaSetController::class, 'index']);
        Route::post('/', [MediaSetController::class, 'store']);
        Route::get('/{id}', [MediaSetController::class, 'show']);
        Route::patch('/{id}', [MediaSetController::class, 'update']);
        Route::delete('/{id}', [MediaSetController::class, 'destroy']);
        Route::post('/reorder', [MediaSetController::class, 'reorder']);

        // Media within a set
        Route::prefix('{setId}/media')->group(function () {
            Route::get('/', [MediaController::class, 'getSetMedia']);
            Route::post('/', [MediaController::class, 'uploadToSet']);
            Route::delete('/{mediaId}', [MediaController::class, 'deleteFromSet']);
            Route::post('/{mediaId}/feedback', [MediaController::class, 'addFeedback']);
        });
    });

    // Media download endpoint (outside of selection/set context)
    Route::get('/media/{mediaUuid}/download', [MediaController::class, 'download']);
});

// Guest Selection Routes (for guest users with temporary tokens)
Route::middleware(['guest.token'])->prefix('guest/selections')->group(function () {
    Route::get('/{id}', [SelectionController::class, 'showGuest']);
    Route::get('/{id}/sets', [MediaSetController::class, 'indexGuest']);
    Route::get('/{id}/sets/{setId}', [MediaSetController::class, 'showGuest']);
    Route::get('/{id}/sets/{setId}/media', [MediaController::class, 'getSetMediaGuest']);
    Route::post('/{id}/complete', [SelectionController::class, 'completeGuest']);
});

// Generate guest token (public endpoint - no auth required)
Route::prefix('selections')->group(function () {
    Route::post('/{id}/guest-token', [GuestSelectionController::class, 'generateToken']);
});

