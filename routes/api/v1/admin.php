<?php

use App\Http\Controllers\V1\Admin\ActivityLogController;
use App\Http\Controllers\V1\Admin\AnalyticsController;
use App\Http\Controllers\V1\Admin\ContactSubmissionController;
use App\Http\Controllers\V1\Admin\DashboardController;
use App\Http\Controllers\V1\Admin\DowngradeRequestController;
use App\Http\Controllers\V1\Admin\EarlyAccessController;
use App\Http\Controllers\V1\Admin\UpgradeRequestController;
use App\Http\Controllers\V1\Admin\PricingController;
use App\Http\Controllers\V1\Admin\ProductController;
use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\Admin\WaitlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes - Version 1
|--------------------------------------------------------------------------
|
| Admin-only routes for managing the platform
| All routes require authentication and admin role
|
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/products/{slug}', [DashboardController::class, 'getProductStats']);
    Route::get('/dashboard/users', [DashboardController::class, 'getUserStats']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{uuid}', [UserController::class, 'show']);
    Route::patch('/users/{uuid}', [UserController::class, 'update']);
    Route::patch('/users/{uuid}/role', [UserController::class, 'updateRole'])->middleware('superadmin');
    Route::patch('/users/{uuid}/suspend', [UserController::class, 'suspend']);
    Route::patch('/users/{uuid}/activate', [UserController::class, 'activate']);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::patch('/products/{slug}', [ProductController::class, 'update']);
    Route::get('/products/{slug}/users', [ProductController::class, 'getProductUsers']);
    Route::get('/products/{slug}/stats', [ProductController::class, 'getProductStats']);

    // Early Access (requests routes first so /early-access/requests is not matched by /early-access/{uuid})
    Route::get('/early-access', [EarlyAccessController::class, 'index']);
    Route::post('/early-access', [EarlyAccessController::class, 'store']);
    Route::get('/early-access/requests', [EarlyAccessController::class, 'listRequests']);
    Route::get('/early-access/requests/{uuid}', [EarlyAccessController::class, 'showRequest']);
    Route::post('/early-access/requests/{uuid}/approve', [EarlyAccessController::class, 'approveRequest']);
    Route::post('/early-access/requests/{uuid}/reject', [EarlyAccessController::class, 'rejectRequest']);
    Route::post('/early-access/requests/bulk-approve', [EarlyAccessController::class, 'bulkApprove']);
    Route::post('/early-access/requests/bulk-reject', [EarlyAccessController::class, 'bulkReject']);
    Route::post('/early-access/rollout-feature', [EarlyAccessController::class, 'rolloutFeature']);
    Route::post('/early-access/release-version', [EarlyAccessController::class, 'updateReleaseVersion']);
    Route::get('/early-access/{uuid}', [EarlyAccessController::class, 'show']);
    Route::patch('/early-access/{uuid}', [EarlyAccessController::class, 'update']);
    Route::delete('/early-access/{uuid}', [EarlyAccessController::class, 'destroy']);

    // Contact form submissions
    Route::get('/contact-submissions', [ContactSubmissionController::class, 'index']);
    Route::get('/contact-submissions/{uuid}', [ContactSubmissionController::class, 'show']);

    // Waitlist
    Route::get('/waitlist', [WaitlistController::class, 'index']);
    Route::get('/waitlist/{uuid}', [WaitlistController::class, 'show']);

    // Newsletter
    Route::get('/newsletter', [\App\Http\Controllers\V1\Admin\NewsletterController::class, 'index']);

    // Downgrade requests (Memora)
    Route::get('/downgrade-requests', [DowngradeRequestController::class, 'index']);
    Route::get('/downgrade-requests/{uuid}', [DowngradeRequestController::class, 'show']);
    Route::post('/downgrade-requests/{uuid}/generate-invoice', [DowngradeRequestController::class, 'generateInvoice']);
    Route::post('/downgrade-requests/{uuid}/cancel', [DowngradeRequestController::class, 'cancel']);

    // Upgrade requests (Memora)
    Route::get('/upgrade-requests', [UpgradeRequestController::class, 'index']);
    Route::get('/upgrade-requests/{uuid}', [UpgradeRequestController::class, 'show']);
    Route::post('/upgrade-requests/{uuid}/generate-invoice', [UpgradeRequestController::class, 'generateInvoice']);
    Route::post('/upgrade-requests/{uuid}/cancel', [UpgradeRequestController::class, 'cancel']);

    // Memora Pricing (fixed tiers + BYO config + addons)
    Route::get('/pricing/tiers', [PricingController::class, 'tiers']);
    Route::post('/pricing/tiers', [PricingController::class, 'storeTier']);
    Route::get('/pricing/tiers/{slug}', [PricingController::class, 'showTier']);
    Route::patch('/pricing/tiers/{slug}', [PricingController::class, 'updateTier']);
    Route::delete('/pricing/tiers/{slug}', [PricingController::class, 'destroyTier']);
    Route::get('/pricing/byo-config', [PricingController::class, 'byoConfig']);
    Route::patch('/pricing/byo-config', [PricingController::class, 'updateByoConfig']);
    Route::get('/pricing/byo-addon-slugs', [PricingController::class, 'byoAddonSlugs']);
    Route::get('/pricing/byo-addons', [PricingController::class, 'byoAddons']);
    Route::post('/pricing/byo-addons', [PricingController::class, 'storeByoAddon']);
    Route::patch('/pricing/byo-addons/{id}', [PricingController::class, 'updateByoAddon']);
    Route::delete('/pricing/byo-addons/{id}', [PricingController::class, 'destroyByoAddon']);

    // Analytics
    Route::get('/analytics/activity', [AnalyticsController::class, 'getActivityLogs']);
    Route::get('/analytics/users/{uuid}/activity', [AnalyticsController::class, 'getUserActivity']);
    Route::get('/analytics/products/{slug}/activity', [AnalyticsController::class, 'getProductActivity']);

    // Activity Logs (super admin only)
    Route::middleware('superadmin')->prefix('activity-logs')->group(function () {
        Route::get('/users', [ActivityLogController::class, 'getUserActivityLogs']);
        Route::get('/admins', [ActivityLogController::class, 'getAdminActivityLogs']);
        Route::get('/statistics', [ActivityLogController::class, 'getStatistics']);
    });
});
