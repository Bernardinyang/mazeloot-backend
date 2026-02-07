<?php

namespace App\Http\Controllers\V1\Admin;

use App\Domains\Memora\Models\MemoraDowngradeRequest;
use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DowngradeRequestController extends Controller
{
    public function __construct(
        protected MemoraSubscriptionService $subscriptionService,
        protected NotificationService $notificationService
    ) {}

    /**
     * List downgrade requests.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MemoraDowngradeRequest::with('user');

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

    /**
     * Get single downgrade request.
     */
    public function show(string $uuid): JsonResponse
    {
        $req = MemoraDowngradeRequest::with('user')->find($uuid);

        if (! $req) {
            return ApiResponse::errorNotFound('Downgrade request not found');
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
                'confirm_token_expires_at' => $req->confirm_token_expires_at?->toIso8601String(),
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

    /**
     * Generate invoice / checkout link for a downgrade request.
     */
    public function generateInvoice(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'target_tier' => 'required|string|in:starter,pro,studio,business',
            'billing_cycle' => 'nullable|string|in:monthly,annual',
            'currency' => 'nullable|string|in:usd,eur,gbp,ngn,zar,kes,ghs,jpy,cad,aud',
            'payment_provider' => 'nullable|string|in:stripe,paypal,paystack,flutterwave',
            'send_to_user' => 'nullable|boolean',
        ]);

        $downgradeRequest = MemoraDowngradeRequest::with('user')->find($uuid);

        if (! $downgradeRequest) {
            return ApiResponse::errorNotFound('Downgrade request not found');
        }

        if ($downgradeRequest->status !== 'pending') {
            return ApiResponse::errorBadRequest('Request is no longer pending');
        }

        $user = $downgradeRequest->user;
        $admin = Auth::user();
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        if ($validated['target_tier'] === 'starter') {
            $token = Str::random(64);
            $expiresAt = now()->addDays(7);

            $downgradeRequest->update([
                'target_tier' => 'starter',
                'confirm_token' => $token,
                'confirm_token_expires_at' => $expiresAt,
            ]);

            $link = "{$frontendUrl}/memora/downgrade/confirm?token={$token}";

            Log::info('Downgrade link generated', [
                'request_uuid' => $uuid,
                'admin_uuid' => $admin->uuid,
                'action' => 'generate_downgrade_link',
            ]);

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'downgrade_invoice_generated',
                    $downgradeRequest,
                    'Admin generated downgrade link',
                    ['target_tier' => 'starter'],
                    $admin,
                    $request
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to log downgrade activity', ['error' => $e->getMessage()]);
            }

            if (! empty($validated['send_to_user'])) {
                $this->notificationService->create(
                    $user->uuid,
                    'memora',
                    'downgrade_link',
                    'Downgrade confirmation link',
                    'Use the link below to confirm your switch to the Starter plan. Your current plan will be cancelled.',
                    null,
                    null,
                    $link,
                    ['downgrade_request_uuid' => $uuid]
                );
            }

            return response()->json([
                'data' => [
                    'link' => $link,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
            ]);
        }

        $billingCycle = $validated['billing_cycle'] ?? 'monthly';
        $currency = $validated['currency'] ?? 'usd';
        $paymentProvider = $validated['payment_provider'] ?? 'stripe';

        try {
            $result = $this->subscriptionService->createCheckoutSessionForDowngrade(
                $user,
                $validated['target_tier'],
                $billingCycle,
                $paymentProvider,
                $currency,
                $uuid
            );
        } catch (\Throwable $e) {
            Log::warning('Downgrade checkout creation failed', ['request_uuid' => $uuid, 'error' => $e->getMessage()]);

            return ApiResponse::errorBadRequest($e->getMessage());
        }

        $checkoutUrl = $result['checkout_url'] ?? null;
        $downgradeRequest->update([
            'target_tier' => $validated['target_tier'],
            'checkout_session_id' => $result['session_id'] ?? null,
            'checkout_url' => $checkoutUrl,
        ]);

        Log::info('Downgrade checkout generated', [
            'request_uuid' => $uuid,
            'admin_uuid' => $admin->uuid,
            'action' => 'generate_downgrade_checkout',
        ]);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'downgrade_invoice_generated',
                $downgradeRequest,
                'Admin generated downgrade checkout',
                ['target_tier' => $validated['target_tier']],
                $admin,
                $request
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to log downgrade activity', ['error' => $e->getMessage()]);
        }

        if (! empty($validated['send_to_user']) && $checkoutUrl) {
            $this->notificationService->create(
                $user->uuid,
                'memora',
                'downgrade_checkout',
                'Complete your plan change',
                'Go to Plan Summary to review and complete your switch to the '.ucfirst($validated['target_tier']).' plan.',
                null,
                null,
                \App\Support\MemoraFrontendUrls::planSummaryPath(true),
                ['downgrade_request_uuid' => $uuid]
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
        $downgradeRequest = MemoraDowngradeRequest::find($uuid);

        if (! $downgradeRequest) {
            return ApiResponse::errorNotFound('Downgrade request not found');
        }

        if ($downgradeRequest->status !== 'pending') {
            return ApiResponse::errorBadRequest('Request is no longer pending');
        }

        $admin = $request->user() ?? Auth::user();
        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'downgrade_request_cancelled',
                $downgradeRequest,
                'Admin cancelled downgrade request',
                [
                    'downgrade_request_uuid' => $uuid,
                    'affected_user_uuid' => $downgradeRequest->user_uuid,
                    'target_tier' => $downgradeRequest->target_tier,
                ],
                $admin,
                $request
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to log downgrade activity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'downgrade_request_uuid' => $uuid,
            ]);
        }

        $downgradeRequest->update(['status' => 'cancelled']);

        Log::info('Downgrade request cancelled', [
            'request_uuid' => $uuid,
            'admin_uuid' => Auth::user()?->uuid,
        ]);

        return response()->json(['data' => ['status' => 'cancelled']]);
    }
}
