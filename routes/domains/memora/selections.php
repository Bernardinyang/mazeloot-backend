<?php

use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\MediaSetController;
use App\Domains\Memora\Controllers\V1\SelectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated Selection Routes
|--------------------------------------------------------------------------
|
| Routes for authenticated users managing their selections
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
    Route::post('/{id}/cover-photo', [SelectionController::class, 'setCoverPhoto']);
    Route::get('/{id}/selected', [SelectionController::class, 'getSelectedMedia']);
    Route::get('/{id}/filenames', [SelectionController::class, 'getSelectedFilenames']);
    Route::post('/{id}/reset-limit', [SelectionController::class, 'resetSelectionLimit']);

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
            Route::post('/move', [MediaController::class, 'moveToSet']);
            Route::post('/copy', [MediaController::class, 'copyToSet']);
            Route::patch('/{mediaId}/rename', [MediaController::class, 'rename']);
            Route::patch('/{mediaId}/replace', [MediaController::class, 'replace']);
            Route::post('/{mediaId}/star', [MediaController::class, 'toggleStar']);
            Route::delete('/{mediaId}', [MediaController::class, 'deleteFromSet']);
            Route::post('/{mediaId}/feedback', [MediaController::class, 'addFeedback']);
        });
    });

    // Media download endpoint (outside of selection/set context)
    Route::get('/media/{mediaUuid}/download', [MediaController::class, 'download']);
});

