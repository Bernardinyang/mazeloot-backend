<?php

use App\Domains\Memora\Controllers\V1\CollectionController;
use App\Domains\Memora\Controllers\V1\CoverLayoutController;
use App\Domains\Memora\Controllers\V1\CoverStyleController;
use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\ProjectController;
use App\Domains\Memora\Controllers\V1\ProofingController;
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
        Route::post('/{id}/star', [ProjectController::class, 'toggleStar']);
        Route::get('/{id}/phases', [ProjectController::class, 'phases']);

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

    // MemoraMedia - General operations (not set-specific)
    Route::prefix('media')->group(function () {
        Route::get('/phase/{phaseType}/{phaseId}', [MediaController::class, 'getPhaseMedia']);
        Route::post('/move-between-phases', [MediaController::class, 'moveBetweenPhases']);
    });
});

// Cover Styles - Public endpoints (frontend needs to fetch these)
Route::prefix('cover-styles')->group(function () {
    Route::get('/', [CoverStyleController::class, 'index']);
    Route::get('/{uuid}', [CoverStyleController::class, 'show']);
    Route::get('/slug/{slug}', [CoverStyleController::class, 'showBySlug']);
});

// Cover Layouts - Public endpoints (frontend needs to fetch these)
Route::prefix('cover-layouts')->group(function () {
    Route::get('/', [CoverLayoutController::class, 'index']);
    Route::get('/{uuid}', [CoverLayoutController::class, 'show']);
    Route::get('/slug/{slug}', [CoverLayoutController::class, 'showBySlug']);
});
