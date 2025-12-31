<?php

use App\Domains\Memora\Controllers\V1\ProofingApprovalRequestController;
use App\Domains\Memora\Controllers\V1\ClosureRequestController;
use App\Domains\Memora\Controllers\V1\CollectionController;
use App\Domains\Memora\Controllers\V1\CoverLayoutController;
use App\Domains\Memora\Controllers\V1\CoverStyleController;
use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\MediaSetController;
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
    // Proofing (standalone routes)
    Route::get('/proofing', [ProofingController::class, 'index']);
    Route::post('/proofing', [ProofingController::class, 'storeStandalone']);
    Route::get('/proofing/{id}', [ProofingController::class, 'showStandalone']);
    Route::patch('/proofing/{id}', [ProofingController::class, 'updateStandalone']);
    Route::delete('/proofing/{id}', [ProofingController::class, 'destroyStandalone']);
    Route::post('/proofing/{id}/publish', [ProofingController::class, 'publishStandalone']);
    Route::post('/proofing/{id}/star', [ProofingController::class, 'toggleStarStandalone']);
    Route::post('/proofing/{id}/cover-photo', [ProofingController::class, 'setCoverPhotoStandalone']);
    Route::post('/proofing/{id}/recover', [ProofingController::class, 'recoverStandalone']);
    Route::post('/proofing/{id}/revisions', [ProofingController::class, 'uploadRevisionStandalone']);

    // Closure Requests
    Route::post('/closure-requests', [ClosureRequestController::class, 'store']);
    Route::get('/media/{mediaId}/closure-requests', [ClosureRequestController::class, 'getByMedia']);

    // Approval Requests
    Route::post('/approval-requests', [ProofingApprovalRequestController::class, 'store']);
    Route::get('/media/{mediaId}/approval-requests', [ProofingApprovalRequestController::class, 'getByMedia']);

    // Media Sets within a standalone proofing
    Route::prefix('proofing/{proofingId}/sets')->group(function () {
        Route::get('/', [MediaSetController::class, 'indexForProofing']);
        Route::post('/', [MediaSetController::class, 'storeForProofing']);
        Route::get('/{id}', [MediaSetController::class, 'showForProofing']);
        Route::patch('/{id}', [MediaSetController::class, 'updateForProofing']);
        Route::delete('/{id}', [MediaSetController::class, 'destroyForProofing']);
        Route::post('/reorder', [MediaSetController::class, 'reorderForProofing']);

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
            Route::patch('/{mediaId}/feedback/{feedbackId}', [MediaController::class, 'updateFeedback']);
            Route::delete('/{mediaId}/feedback/{feedbackId}', [MediaController::class, 'deleteFeedback']);
        });
    });

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
            Route::delete('/{id}', [ProofingController::class, 'destroy']);
            Route::post('/{id}/publish', [ProofingController::class, 'publish']);
            Route::post('/{id}/star', [ProofingController::class, 'toggleStar']);
            Route::post('/{id}/cover-photo', [ProofingController::class, 'setCoverPhoto']);
            Route::post('/{id}/recover', [ProofingController::class, 'recover']);
            Route::post('/{id}/revisions', [ProofingController::class, 'uploadRevision']);
            Route::post('/{id}/complete', [ProofingController::class, 'complete']);
            Route::post('/{id}/move-to-collection', [ProofingController::class, 'moveToCollection']);

            // Media Sets within a project-based proofing
            Route::prefix('{proofingId}/sets')->group(function () {
                Route::get('/', [MediaSetController::class, 'indexForProofing']);
                Route::post('/', [MediaSetController::class, 'storeForProofing']);
                Route::get('/{id}', [MediaSetController::class, 'showForProofing']);
                Route::patch('/{id}', [MediaSetController::class, 'updateForProofing']);
                Route::delete('/{id}', [MediaSetController::class, 'destroyForProofing']);
                Route::post('/reorder', [MediaSetController::class, 'reorderForProofing']);

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
                    Route::patch('/{mediaId}/feedback/{feedbackId}', [MediaController::class, 'updateFeedback']);
                    Route::delete('/{mediaId}/feedback/{feedbackId}', [MediaController::class, 'deleteFeedback']);
                });
            });
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
        Route::get('/{id}/revisions', [MediaController::class, 'getRevisions']);
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
