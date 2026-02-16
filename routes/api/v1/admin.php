<?php

use App\Http\Controllers\V1\Admin\ActivityLogController;
use App\Http\Controllers\V1\Admin\AnalyticsController;
use App\Http\Controllers\V1\Admin\CacheController;
use App\Http\Controllers\V1\Admin\ContactSubmissionController;
use App\Http\Controllers\V1\Admin\DashboardController;
use App\Http\Controllers\V1\Admin\DowngradeRequestController;
use App\Http\Controllers\V1\Admin\EarlyAccessController;
use App\Http\Controllers\V1\Admin\FaqController;
use App\Http\Controllers\V1\Admin\HealthController;
use App\Http\Controllers\V1\Admin\LogsController;
use App\Http\Controllers\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\V1\Admin\PricingController;
use App\Http\Controllers\V1\Admin\ProductController;
use App\Http\Controllers\V1\Admin\QueueController;
use App\Http\Controllers\V1\Admin\SystemController;
use App\Http\Controllers\V1\Admin\UpgradeRequestController;
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

    // Health & System (super admin only)
    Route::middleware('superadmin')->group(function () {
        Route::get('/health', [HealthController::class, 'index']);
        Route::get('/system', [SystemController::class, 'index']);
        Route::get('/system/connectivity', [SystemController::class, 'connectivity']);
        Route::get('/system/webhooks', [SystemController::class, 'webhooks']);
    });
    Route::get('/queue/failed', [QueueController::class, 'failed']);
    Route::post('/queue/failed/retry', [QueueController::class, 'retryFailed']);
    Route::delete('/queue/failed/{uuid}', [QueueController::class, 'forgetFailed']);
    Route::post('/queue/failed/flush', [QueueController::class, 'flushFailed']);
    Route::post('/queue/restart', [QueueController::class, 'restart']);
    Route::get('/logs/recent', [LogsController::class, 'recent'])->middleware('throttle:30,1');
    Route::post('/logs/clear', [LogsController::class, 'clear']);
    Route::get('/dashboard/products/{slug}', [DashboardController::class, 'getProductStats']);
    Route::get('/dashboard/users', [DashboardController::class, 'getUserStats']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{uuid}', [UserController::class, 'show']);
    Route::get('/users/{uuid}/notifications', [AdminNotificationController::class, 'indexForUser']);
    Route::patch('/users/{uuid}', [UserController::class, 'update']);
    Route::patch('/users/{uuid}/role', [UserController::class, 'updateRole'])->middleware('superadmin');
    Route::patch('/users/{uuid}/suspend', [UserController::class, 'suspend']);
    Route::patch('/users/{uuid}/activate', [UserController::class, 'activate']);

    // Notifications (superadmin: resend web push)
    Route::post('/notifications/{uuid}/resend-push', [AdminNotificationController::class, 'resendPush'])->middleware(['superadmin', 'throttle:10,1']);

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

    // FAQ
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::post('/faqs', [FaqController::class, 'store']);
    Route::get('/faqs/{uuid}', [FaqController::class, 'show']);
    Route::patch('/faqs/{uuid}', [FaqController::class, 'update']);
    Route::delete('/faqs/{uuid}', [FaqController::class, 'destroy']);

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
    Route::get('/analytics/overview', [AnalyticsController::class, 'getOverview']);
    Route::get('/analytics/activity', [AnalyticsController::class, 'getActivityLogs']);
    Route::get('/analytics/users/{uuid}/activity', [AnalyticsController::class, 'getUserActivity']);
    Route::get('/analytics/products/{slug}/activity', [AnalyticsController::class, 'getProductActivity']);

    // Activity Logs: users + admins for any admin; sensitive + statistics for super admin only
    Route::prefix('activity-logs')->group(function () {
        Route::get('/users', [ActivityLogController::class, 'getUserActivityLogs']);
        Route::get('/admins', [ActivityLogController::class, 'getAdminActivityLogs']);
        Route::middleware('superadmin')->group(function () {
            Route::get('/sensitive', [ActivityLogController::class, 'getSensitiveActivityLogs']);
            Route::get('/statistics', [ActivityLogController::class, 'getStatistics']);
        });
    });

    Route::post('/cache/clear', [CacheController::class, 'clear']);
    Route::post('/cache/clear-all-optimize', [CacheController::class, 'clearAllAndOptimize']);
});
