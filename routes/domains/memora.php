<?php

use App\Domains\Memora\Controllers\V1\CollectionController;
use App\Domains\Memora\Controllers\V1\CoverStyleController;
use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\ProjectController;
use App\Domains\Memora\Controllers\V1\ProofingController;
use App\Domains\Memora\Controllers\V1\SelectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Memora Domain Routes
|--------------------------------------------------------------------------
|
| Routes for the Memora photo gallery/collections domain
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Projects
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{id}', [ProjectController::class, 'show']);
        Route::patch('/{id}', [ProjectController::class, 'update']);
        Route::delete('/{id}', [ProjectController::class, 'destroy']);
        Route::get('/{id}/phases', [ProjectController::class, 'phases']);

        // Selections
        Route::prefix('{projectId}/selections')->group(function () {
            Route::post('/', [SelectionController::class, 'store']);
            Route::get('/{id}', [SelectionController::class, 'show']);
            Route::patch('/{id}', [SelectionController::class, 'update']);
            Route::post('/{id}/complete', [SelectionController::class, 'complete']);
            Route::post('/{id}/recover', [SelectionController::class, 'recover']);
            Route::get('/{id}/selected', [SelectionController::class, 'getSelectedMedia']);
            Route::get('/{id}/filenames', [SelectionController::class, 'getSelectedFilenames']);
        });

        // MemoraProofing
        Route::prefix('{projectId}/proofing')->group(function () {
            Route::post('/', [ProofingController::class, 'store']);
            Route::get('/{id}', [ProofingController::class, 'show']);
            Route::patch('/{id}', [ProofingController::class, 'update']);
            Route::post('/{id}/revisions', [ProofingController::class, 'uploadRevision']);
            Route::post('/{id}/complete', [ProofingController::class, 'complete']);
            Route::post('/{id}/move-to-collection', [ProofingController::class, 'moveToCollection']);
        });

        // Collections
        Route::prefix('{projectId}/collections')->group(function () {
            Route::get('/', [CollectionController::class, 'index']);
            Route::post('/', [CollectionController::class, 'store']);
            Route::get('/{id}', [CollectionController::class, 'show']);
            Route::patch('/{id}', [CollectionController::class, 'update']);
            Route::delete('/{id}', [CollectionController::class, 'destroy']);
        });
    });

    // MemoraMedia
    Route::prefix('media')->group(function () {
        Route::get('/phase/{phaseType}/{phaseId}', [MediaController::class, 'getPhaseMedia']);
        Route::post('/move-between-phases', [MediaController::class, 'moveBetweenPhases']);
        Route::post('/{id}/low-res-copy', [MediaController::class, 'generateLowResCopy']);
        Route::patch('/{id}/select', [MediaController::class, 'markSelected']);
        Route::get('/{id}/revisions', [MediaController::class, 'getRevisions']);
        Route::patch('/{id}/complete', [MediaController::class, 'markCompleted']);
        Route::post('/{mediaId}/feedback', [MediaController::class, 'addFeedback']);
    });
});

// Cover Styles - Public endpoints (frontend needs to fetch these)
Route::prefix('cover-styles')->group(function () {
    Route::get('/', [CoverStyleController::class, 'index']);
    Route::get('/{uuid}', [CoverStyleController::class, 'show']);
    Route::get('/slug/{slug}', [CoverStyleController::class, 'showBySlug']);
});
