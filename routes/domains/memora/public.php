<?php

use App\Domains\Memora\Controllers\V1\ClosureRequestController;
use App\Domains\Memora\Controllers\V1\CloudStorageOAuthController;
use App\Domains\Memora\Controllers\V1\GuestProofingController;
use App\Domains\Memora\Controllers\V1\GuestSelectionController;
use App\Domains\Memora\Controllers\V1\ProofingApprovalRequestController;
use App\Domains\Memora\Controllers\V1\PublicCollectionController;
use App\Domains\Memora\Controllers\V1\PublicMediaController;
use App\Domains\Memora\Controllers\V1\PublicMediaSetController;
use App\Domains\Memora\Controllers\V1\PublicProofingController;
use App\Domains\Memora\Controllers\V1\PublicProofingMediaSetController;
use App\Domains\Memora\Controllers\V1\GuestRawFilesController;
use App\Domains\Memora\Controllers\V1\PublicRawFilesController;
use App\Domains\Memora\Controllers\V1\PublicSelectionController;
use App\Domains\Memora\Controllers\V1\PublicSettingsController;
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

// Public Proofing Routes (protected by guest token, not user authentication)
Route::prefix('public/proofing')->group(function () {
    // Check proofing status (truly public - no authentication required)
    Route::get('/{id}/status', [PublicProofingController::class, 'checkStatus']);

    // Verify password (truly public - no authentication required)
    Route::post('/{id}/verify-password', [PublicProofingController::class, 'verifyPassword']);

    // Generate guest token (truly public - no authentication required)
    Route::post('/{id}/token', [GuestProofingController::class, 'generateToken']);

    // Guest Proofing Routes (protected by guest token middleware)
    Route::middleware(['guest.token'])->group(function () {
        Route::get('/{id}', [PublicProofingController::class, 'show']);
        Route::get('/{id}/sets', [PublicProofingMediaSetController::class, 'index']);
        Route::get('/{id}/sets/{setId}', [PublicProofingMediaSetController::class, 'show']);
        Route::get('/{id}/sets/{setId}/media', [PublicMediaController::class, 'getProofingSetMedia']);
        Route::get('/{id}/filenames', [PublicProofingController::class, 'getSelectedFilenames']);
        Route::post('/{id}/complete', [PublicProofingController::class, 'complete']);
        Route::patch('/{id}/media/{mediaId}/toggle-selected', [PublicMediaController::class, 'toggleProofingSelected']);
        Route::post('/{id}/sets/{setId}/media/{mediaId}/feedback', [PublicMediaController::class, 'addProofingFeedback']);
        Route::patch('/{id}/sets/{setId}/media/{mediaId}/feedback/{feedbackId}', [PublicMediaController::class, 'updateProofingFeedback']);
        Route::delete('/{id}/sets/{setId}/media/{mediaId}/feedback/{feedbackId}', [PublicMediaController::class, 'deleteProofingFeedback']);
        Route::post('/{id}/media/{mediaId}/approve', [PublicMediaController::class, 'approveProofingMedia']);
    });
});

// Public Closure Request Routes (no authentication required)
Route::prefix('public/closure-requests')->group(function () {
    Route::get('/{token}', [ClosureRequestController::class, 'showByToken']);
    Route::post('/{token}/approve', [ClosureRequestController::class, 'approve']);
    Route::post('/{token}/reject', [ClosureRequestController::class, 'reject']);
});

// Public Approval Request Routes (no authentication required)
Route::prefix('public/approval-requests')->group(function () {
    Route::get('/{token}', [ProofingApprovalRequestController::class, 'showByToken']);
    Route::post('/{token}/approve', [ProofingApprovalRequestController::class, 'approve']);
    Route::post('/{token}/reject', [ProofingApprovalRequestController::class, 'reject']);
});

// Public Settings Routes (no authentication required)
Route::prefix('public/settings')->group(function () {
    Route::get('/', [PublicSettingsController::class, 'index']);
});

// Public Homepage Routes (no authentication required)
Route::prefix('public/homepage')->group(function () {
    Route::post('/verify-password', [PublicSettingsController::class, 'verifyHomepagePassword']);
    Route::get('/collections', [PublicSettingsController::class, 'getHomepageCollections']);
    Route::get('/social-links', [PublicSettingsController::class, 'getSocialLinks']);
    Route::get('/featured-media', [PublicSettingsController::class, 'getFeaturedMedia']);
});

// Public Collection Routes (no authentication required for published collections)
// Format: /memora/{subdomainOrUsername}/collections/{id}
Route::prefix('memora/{subdomainOrUsername}/collections')->group(function () {
    // Check collection status (truly public - no authentication required)
    Route::get('/{id}/status', [PublicCollectionController::class, 'checkStatus']);

    // Verify password (truly public - no authentication required)
    Route::post('/{id}/verify-password', [PublicCollectionController::class, 'verifyPassword']);

    // Verify download PIN (truly public - no authentication required)
    Route::post('/{id}/verify-download-pin', [PublicCollectionController::class, 'verifyDownloadPin']);

    // Verify client password (truly public - no authentication required)
    Route::post('/{id}/verify-client-password', [PublicCollectionController::class, 'verifyClientPassword']);

    // Get media sets for collection (public - no authentication required)
    Route::get('/{id}/sets', [PublicCollectionController::class, 'getSets']);
    Route::get('/{id}/media-sets', [PublicCollectionController::class, 'getSets']); // Alias for frontend compatibility

    // Get media for a specific set (public - no authentication required)
    Route::get('/{id}/sets/{setId}/media', [PublicMediaController::class, 'getCollectionSetMedia']);

    // Toggle favourite for collection media (public - no authentication required)
    // Must be defined before any other /{id}/media/{mediaId} routes to avoid conflicts
    Route::post('/{id}/media/{mediaId}/favourite', [PublicMediaController::class, 'toggleCollectionFavourite']);

    // Toggle private status for collection media (public - requires client verification)
    Route::post('/{id}/media/{mediaId}/toggle-private', [PublicMediaController::class, 'toggleMediaPrivate']);

    // Download media from collection (public - no authentication required)
    Route::get('/{id}/media/{mediaId}/download', [PublicMediaController::class, 'downloadCollectionMedia']);

    // ZIP download routes
    Route::post('/{id}/download/zip', [PublicCollectionController::class, 'initiateZipDownload']);
    Route::get('/{id}/download/zip/{token}/status', [PublicCollectionController::class, 'getZipDownloadStatus']);
    Route::get('/{id}/download/zip/{token}', [PublicCollectionController::class, 'downloadZip']);

    // Track activities (public - no authentication required)
    Route::post('/{id}/track-email-registration', [\App\Domains\Memora\Controllers\V1\CollectionActivityController::class, 'trackEmailRegistration']);
    Route::post('/{id}/track-share-link', [\App\Domains\Memora\Controllers\V1\CollectionActivityController::class, 'trackShareLinkClick']);
    Route::post('/{id}/media/{mediaId}/track-private-access', [\App\Domains\Memora\Controllers\V1\CollectionActivityController::class, 'trackPrivatePhotoAccess']);

    // Get collection (public - no authentication required for published collections)
    // Must be last to avoid matching more specific routes
    Route::get('/{id}', [PublicCollectionController::class, 'show']);
});

// Public Raw Files Routes (no authentication required for published raw files phases)
// Format: /memora/{subdomainOrUsername}/raw-files/{id}
Route::prefix('memora/{subdomainOrUsername}/raw-files')->group(function () {
    // Check raw files status (truly public - no authentication required)
    Route::get('/{id}/status', [PublicRawFilesController::class, 'checkStatus']);

    // Verify password (truly public - no authentication required)
    Route::post('/{id}/verify-password', [PublicRawFilesController::class, 'verifyPassword']);

    // Verify download PIN (truly public - no authentication required)
    Route::post('/{id}/verify-download-pin', [PublicRawFilesController::class, 'verifyDownloadPin']);

    // Generate guest token (truly public - no authentication required)
    Route::post('/{id}/token', [GuestRawFilesController::class, 'generateToken']);

    // Get raw files phase (public - no authentication required)
    Route::get('/{id}', [PublicRawFilesController::class, 'show']);

    // Get media sets for raw files phase (public - no authentication required)
    Route::get('/{id}/sets', [PublicRawFilesController::class, 'getSets']);
    Route::get('/{id}/media-sets', [PublicRawFilesController::class, 'getSets']); // Alias for frontend compatibility

    // Get media for a specific set (public - no authentication required)
    Route::get('/{id}/sets/{setId}/media', [PublicMediaController::class, 'getRawFilesSetMedia']);

    // Download media from raw files phase (public - no authentication required)
    Route::get('/{id}/media/{mediaId}/download', [PublicMediaController::class, 'downloadRawFilesMedia']);
});

// Cloud Storage OAuth Routes (public - no authentication required)
Route::prefix('cloud-storage/oauth')->group(function () {
    Route::post('/initiate', [CloudStorageOAuthController::class, 'initiate']);
    Route::get('/{service}/callback', [CloudStorageOAuthController::class, 'callback']);
});

// Public Media Closure Requests (guest token required)
Route::prefix('public')->middleware(['guest.token'])->group(function () {
    Route::get('/media/{mediaId}/closure-requests', [ClosureRequestController::class, 'getByMediaPublic']);
    Route::get('/media/{mediaId}/approval-requests', [ProofingApprovalRequestController::class, 'getByMediaPublic']);
    Route::get('/media/{mediaId}/revisions', [PublicMediaController::class, 'getRevisions']);
});
