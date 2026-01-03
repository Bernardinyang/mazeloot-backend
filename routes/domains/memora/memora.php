<?php

use App\Domains\Memora\Controllers\V1\ClosureRequestController;
use App\Domains\Memora\Controllers\V1\CollectionController;
use App\Domains\Memora\Controllers\V1\CoverLayoutController;
use App\Domains\Memora\Controllers\V1\CoverStyleController;
use App\Domains\Memora\Controllers\V1\EmailNotificationController;
use App\Domains\Memora\Controllers\V1\MediaController;
use App\Domains\Memora\Controllers\V1\MediaSetController;
use App\Domains\Memora\Controllers\V1\PresetController;
use App\Domains\Memora\Controllers\V1\ProjectController;
use App\Domains\Memora\Controllers\V1\ProofingApprovalRequestController;
use App\Domains\Memora\Controllers\V1\ProofingController;
use App\Domains\Memora\Controllers\V1\SettingsController;
use App\Domains\Memora\Controllers\V1\SocialLinkController;
use App\Domains\Memora\Controllers\V1\WatermarkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Memora Domain Routes
|--------------------------------------------------------------------------
|
| Routes for the Memora photo gallery/collections domain
|
*/

Route::middleware(['auth:sanctum'])->prefix('memora')->group(function () {
    // Proofing (unified routes - works for both standalone and project-based)
    // For project-based: pass ?projectId=xxx as query parameter
    Route::get('/proofing', [ProofingController::class, 'index']);
    Route::post('/proofing', [ProofingController::class, 'store']);
    Route::get('/proofing/{id}', [ProofingController::class, 'show']);
    Route::patch('/proofing/{id}', [ProofingController::class, 'update']);
    Route::delete('/proofing/{id}', [ProofingController::class, 'destroy']);
    Route::post('/proofing/{id}/publish', [ProofingController::class, 'publish']);
    Route::post('/proofing/{id}/star', [ProofingController::class, 'toggleStar']);
    Route::post('/proofing/{id}/cover-photo', [ProofingController::class, 'setCoverPhoto']);
    Route::post('/proofing/{id}/recover', [ProofingController::class, 'recover']);
    Route::post('/proofing/{id}/revisions', [ProofingController::class, 'uploadRevision']);
    Route::post('/proofing/{id}/complete', [ProofingController::class, 'complete']);
    Route::post('/proofing/{id}/move-to-collection', [ProofingController::class, 'moveToCollection']);

    // Closure Requests
    Route::post('/closure-requests', [ClosureRequestController::class, 'store']);
    Route::get('/media/{mediaId}/closure-requests', [ClosureRequestController::class, 'getByMedia']);

    // Approval Requests
    Route::post('/approval-requests', [ProofingApprovalRequestController::class, 'store']);
    Route::get('/media/{mediaId}/approval-requests', [ProofingApprovalRequestController::class, 'getByMedia']);

    // Media Sets within proofing (unified - works for both standalone and project-based)
    // For project-based: pass ?projectId=xxx as query parameter
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
            Route::post('/{mediaId}/watermark', [MediaController::class, 'applyWatermark']);
            Route::delete('/{mediaId}/watermark', [MediaController::class, 'removeWatermark']);
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
    });

    // Collections (unified routes - works for both standalone and project-based)
    // For project-based: pass ?projectId=xxx as query parameter
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']);
    Route::get('/collections/{id}', [CollectionController::class, 'show']);
    Route::patch('/collections/{id}', [CollectionController::class, 'update']);
    Route::delete('/collections/{id}', [CollectionController::class, 'destroy']);
    Route::post('/collections/{id}/star', [CollectionController::class, 'toggleStar']);

    // Media Sets within collections (unified - works for both standalone and project-based)
    // For project-based: pass ?projectId=xxx as query parameter
    Route::prefix('collections/{collectionId}/sets')->group(function () {
        Route::get('/', [MediaSetController::class, 'indexForCollection']);
        Route::post('/', [MediaSetController::class, 'storeForCollection']);
        Route::get('/{id}', [MediaSetController::class, 'showForCollection']);
        Route::patch('/{id}', [MediaSetController::class, 'updateForCollection']);
        Route::delete('/{id}', [MediaSetController::class, 'destroyForCollection']);
        Route::post('/reorder', [MediaSetController::class, 'reorderForCollection']);

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

    // MemoraMedia - General operations (not set-specific)
    Route::prefix('media')->group(function () {
        Route::get('/phase/{phaseType}/{phaseId}', [MediaController::class, 'getPhaseMedia']);
        Route::post('/move-between-phases', [MediaController::class, 'moveBetweenPhases']);
        Route::get('/{id}/revisions', [MediaController::class, 'getRevisions']);
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::patch('/branding', [SettingsController::class, 'updateBranding']);
        Route::patch('/preference', [SettingsController::class, 'updatePreference']);
        Route::patch('/homepage', [SettingsController::class, 'updateHomepage']);
        Route::patch('/email', [SettingsController::class, 'updateEmail']);

        // Email Notifications
        Route::get('/notifications', [EmailNotificationController::class, 'index']);
        Route::patch('/notifications', [EmailNotificationController::class, 'update']);

        // Social Links
        Route::prefix('social-links')->group(function () {
            Route::get('/', [SocialLinkController::class, 'index']);
            Route::get('/platforms', [SocialLinkController::class, 'getPlatforms']);
            Route::post('/', [SocialLinkController::class, 'store']);
            Route::patch('/{id}', [SocialLinkController::class, 'update']);
            Route::delete('/{id}', [SocialLinkController::class, 'destroy']);
            Route::post('/reorder', [SocialLinkController::class, 'reorder']);
        });

        // Watermarks
        Route::prefix('watermarks')->group(function () {
            Route::get('/', [WatermarkController::class, 'index']);
            Route::post('/', [WatermarkController::class, 'store']);
            Route::post('/upload-image', [WatermarkController::class, 'uploadImage']);
            Route::get('/{id}', [WatermarkController::class, 'show']);
            Route::patch('/{id}', [WatermarkController::class, 'update']);
            Route::delete('/{id}', [WatermarkController::class, 'destroy']);
            Route::post('/{id}/duplicate', [WatermarkController::class, 'duplicate']);
            Route::get('/{id}/usage', [WatermarkController::class, 'usage']);
        });

        // Presets
        Route::prefix('presets')->group(function () {
            Route::get('/', [PresetController::class, 'index']);
            Route::post('/', [PresetController::class, 'store']);
            Route::patch('/reorder', [PresetController::class, 'reorder']);
            Route::get('/{id}', [PresetController::class, 'show']);
            Route::patch('/{id}', [PresetController::class, 'update']);
            Route::delete('/{id}', [PresetController::class, 'destroy']);
            Route::post('/{id}/duplicate', [PresetController::class, 'duplicate']);
            Route::post('/{id}/apply-to-collection/{collectionId}', [PresetController::class, 'applyToCollection']);
            Route::get('/{id}/usage', [PresetController::class, 'usage']);
            Route::patch('/{id}/set-default', [PresetController::class, 'setDefault']);
        });
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
