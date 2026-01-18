<?php

use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\MediaSetController;
use App\Domains\Memora\Controllers\V1\RawFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated Raw File Routes
|--------------------------------------------------------------------------
|
| Routes for authenticated users managing their raw files
|
*/

// Authenticated Raw File Routes
Route::middleware(['auth:sanctum'])->prefix('raw-files')->group(function () {
    // Raw File CRUD
    Route::get('/', [RawFileController::class, 'index']);
    Route::post('/', [RawFileController::class, 'store']);
    Route::get('/{id}', [RawFileController::class, 'show']);
    Route::patch('/{id}', [RawFileController::class, 'update']);
    Route::delete('/{id}', [RawFileController::class, 'destroy']);
    Route::post('/{id}/publish', [RawFileController::class, 'publish']);
    Route::post('/{id}/recover', [RawFileController::class, 'recover']);
    Route::post('/{id}/star', [RawFileController::class, 'toggleStar']);
    Route::post('/{id}/duplicate', [RawFileController::class, 'duplicate']);
    Route::post('/{id}/cover-photo', [RawFileController::class, 'setCoverPhoto']);
    Route::get('/{id}/selected', [RawFileController::class, 'getSelectedMedia']);
    Route::get('/{id}/filenames', [RawFileController::class, 'getSelectedFilenames']);
    Route::post('/{id}/reset-limit', [RawFileController::class, 'resetRawFileLimit']);

    // Media Sets within a raw file
    Route::prefix('{rawFileId}/sets')->group(function () {
        Route::get('/', [MediaSetController::class, 'indexForRawFile']);
        Route::post('/', [MediaSetController::class, 'storeForRawFile']);
        Route::get('/{id}', [MediaSetController::class, 'showForRawFile']);
        Route::patch('/{id}', [MediaSetController::class, 'updateForRawFile']);
        Route::delete('/{id}', [MediaSetController::class, 'destroyForRawFile']);
        Route::post('/reorder', [MediaSetController::class, 'reorderForRawFile']);

        // Media within a set
        Route::prefix('{setId}/media')->group(function () {
            Route::get('/', [MediaController::class, 'getSetMedia']);
            Route::post('/', [MediaController::class, 'uploadToSet']);
            Route::post('/move', [MediaController::class, 'moveToSet']);
            Route::post('/copy', [MediaController::class, 'copyToSet']);
            Route::patch('/{mediaId}/rename', [MediaController::class, 'rename']);
            Route::patch('/{mediaId}/replace', [MediaController::class, 'replace']);
            Route::post('/{mediaId}/watermark', [MediaController::class, 'applyWatermark']);
            Route::delete('/{mediaId}/watermark', [MediaController::class, 'removeWatermark']);
            Route::post('/{mediaId}/star', [MediaController::class, 'toggleStar']);
            Route::delete('/{mediaId}', [MediaController::class, 'deleteFromSet']);
        });
    });

    // Media download endpoint (outside of raw file/set context)
    Route::get('/media/{mediaUuid}/download', [MediaController::class, 'download'])->name('memora.media.download');
    Route::get('/media/{mediaUuid}/serve', [MediaController::class, 'serve'])->name('memora.media.serve');

    // Starred media endpoint
    Route::get('/media/starred', [MediaController::class, 'getStarredMedia']);
});
