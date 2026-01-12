<?php

use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\MediaSetController;
use App\Domains\Memora\Controllers\V1\RawFilesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated Raw Files Routes
|--------------------------------------------------------------------------
|
| Routes for authenticated users managing their raw files phases
|
*/

// Authenticated Raw Files Routes
Route::middleware(['auth:sanctum'])->prefix('raw-files')->group(function () {
    // Raw Files CRUD
    Route::get('/', [RawFilesController::class, 'index']);
    Route::post('/', [RawFilesController::class, 'store']);
    Route::get('/{id}', [RawFilesController::class, 'show']);
    Route::patch('/{id}', [RawFilesController::class, 'update']);
    Route::delete('/{id}', [RawFilesController::class, 'destroy']);
    Route::post('/{id}/publish', [RawFilesController::class, 'publish']);
    Route::post('/{id}/star', [RawFilesController::class, 'toggleStar']);
    Route::post('/{id}/duplicate', [RawFilesController::class, 'duplicate']);
    Route::post('/{id}/cover-photo', [RawFilesController::class, 'setCoverPhoto']);

    // Media Sets within a raw files phase
    Route::prefix('{rawFilesId}/sets')->group(function () {
        Route::get('/', [MediaSetController::class, 'indexForRawFiles']);
        Route::post('/', [MediaSetController::class, 'storeForRawFiles']);
        Route::get('/{id}', [MediaSetController::class, 'showForRawFiles']);
        Route::patch('/{id}', [MediaSetController::class, 'updateForRawFiles']);
        Route::delete('/{id}', [MediaSetController::class, 'destroyForRawFiles']);
        Route::post('/reorder', [MediaSetController::class, 'reorderForRawFiles']);

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

    // Media download endpoint (outside of raw files/set context)
    Route::get('/media/{mediaUuid}/download', [MediaController::class, 'download'])->name('memora.raw-files.media.download');
    Route::get('/media/{mediaUuid}/serve', [MediaController::class, 'serve'])->name('memora.raw-files.media.serve');
});
