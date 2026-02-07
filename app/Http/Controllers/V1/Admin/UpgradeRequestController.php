<?php

namespace App\Http\Controllers\V1\Admin;

use App\Domains\Memora\Models\MemoraUpgradeRequest;
use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UpgradeRequestController extends Controller
{
    public function __construct(
        protected MemoraSubscriptionService $subscriptionService,
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MemoraUpgradeRequest::with('user');

        if ($request->has('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $items = $query->orderByDesc('created_at')->paginate($perPage);

        $data = $items->getCollection()->map(fn ($req) => [
            'uuid' => $req->uuid,
            'user' => [
                'uuid' => $req->user->uuid,
                'email' => $req->user->email,
                'first_name' => $req->user->first_name,
                'last_name' => $req->user->last_name,
            ],
            'current_tier' => $req->current_tier,
            'target_tier' => $req->target_tier,
            'status' => $req->status,
            'requested_at' => $req->requested_at?->toIso8601String(),
            'completed_at' => $req->completed_at?->toIso8601String(),
            'created_at' => $req->created_at->toIso8601String(),
        ]);

        $items->setCollection($data);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $req = MemoraUpgradeRequest::with('user')->find($uuid);

        if (! $req) {
            return ApiResponse::errorNotFound('Upgrade request not found');
        }

        $subscription = $this->subscriptionService->getActiveSubscription($req->user);

        return response()->json([
            'data' => [
                'uuid' => $req->uuid,
                'user' => [
                    'uuid' => $req->user->uuid,
                    'email' => $req->user->email,
                    'first_name' => $req->user->first_name,
                    'last_name' => $req->user->last_name,
                ],
                'current_tier' => $req->current_tier,
                'target_tier' => $req->target_tier,
                'status' => $req->status,
                'requested_at' => $req->requested_at?->toIso8601String(),
                'completed_at' => $req->completed_at?->toIso8601String(),
                'notes' => $req->notes,
                'subscription' => $subscription ? [
                    'tier' => $subscription->tier,
                    'billing_cycle' => $subscription->billing_cycle,
                    'current_period_end' => $subscription->getEffectivePeriodEnd()?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    public function generateInvoice(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'billing_cycle' => 'nullable|string|in:monthly,annual',
            'currency' => 'nullable|string|in:usd,eur,gbp,ngn,zar,kes,ghs,jpy,cad,aud',
            'payment_provider' => 'nullable|string|in:stripe,paypal,paystack,flutterwave',
            'send_to_user' => 'nullable|boolean',
        ]);

        $upgradeRequest = MemoraUpgradeRequest::with('user')->find($uuid);

        if (! $upgradeRequest) {
            return ApiResponse::errorNotFound('Upgrade request not found');
        }

        if ($upgradeRequest->status !== 'pending') {
            return ApiResponse::errorBadRequest('Request is no longer pending');
        }

        $user = $upgradeRequest->user;
        $targetTier = $upgradeRequest->target_tier;
        $billingCycle = $validated['billing_cycle'] ?? 'monthly';
        $currency = $validated['currency'] ?? 'usd';
        $paymentProvider = $validated['payment_provider'] ?? 'stripe';

        try {
            $result = $this->subscriptionService->createCheckoutSessionForUpgrade(
                $user,
                $targetTier,
                $billingCycle,
                $paymentProvider,
                $currency,
                $uuid
            );
        } catch (\Throwable $e) {
            Log::warning('Upgrade checkout creation failed', ['request_uuid' => $uuid, 'error' => $e->getMessage()]);

            return ApiResponse::errorBadRequest($e->getMessage());
        }

        $checkoutUrl = $result['checkout_url'] ?? null;
        $upgradeRequest->update([
            'checkout_session_id' => $result['session_id'] ?? null,
            'checkout_url' => $checkoutUrl,
        ]);

        Log::info('Upgrade checkout generated', [
            'request_uuid' => $uuid,
            'admin_uuid' => Auth::user()?->uuid,
            'action' => 'generate_upgrade_checkout',
        ]);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'upgrade_invoice_generated',
                $upgradeRequest,
                'Admin generated upgrade checkout',
                ['target_tier' => $targetTier],
                $request->user(),
                $request
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to log upgrade activity', ['error' => $e->getMessage()]);
        }

        if (! empty($validated['send_to_user']) && $checkoutUrl) {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'upgrade_checkout',
                'Complete your plan upgrade',
                'Go to Plan Summary to review and complete your upgrade to the '.ucfirst($targetTier).' plan.',
                null,
                null,
                \App\Support\MemoraFrontendUrls::planSummaryPath(true),
                ['upgrade_request_uuid' => $uuid]
            );
        }

        return response()->json([
            'data' => [
                'checkout_url' => $result['checkout_url'],
                'expires_at' => null,
            ],
        ]);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $upgradeRequest = MemoraUpgradeRequest::find($uuid);

        if (! $upgradeRequest) {
            return ApiResponse::errorNotFound('Upgrade request not found');
        }

        if ($upgradeRequest->status !== 'pending') {
            return ApiResponse::errorBadRequest('Request is no longer pending');
        }

        $admin = $request->user() ?? Auth::user();
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'upgrade_request_cancelled',
                $upgradeRequest,
                'Admin cancelled upgrade request',
                [
                    'upgrade_request_uuid' => $uuid,
                    'affected_user_uuid' => $upgradeRequest->user_uuid,
                    'target_tier' => $upgradeRequest->target_tier,
                ],
                $admin,
                $request
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to log upgrade activity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'upgrade_request_uuid' => $uuid,
            ]);
        }

        $upgradeRequest->update(['status' => 'cancelled']);

        Log::info('Upgrade request cancelled', [
            'request_uuid' => $uuid,
            'admin_uuid' => Auth::user()?->uuid,
        ]);

        return response()->json(['data' => ['status' => 'cancelled']]);
    }
}
