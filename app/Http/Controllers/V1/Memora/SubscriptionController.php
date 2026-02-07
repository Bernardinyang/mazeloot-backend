<?php

namespace App\Http\Controllers\V1\Memora;

use App\Domains\Memora\Models\MemoraByoConfig;
use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraDowngradeRequest;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraRawFile;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Models\MemoraUpgradeRequest;
use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Jobs\NotifyAdminsDowngradeRequest;
use App\Jobs\NotifyAdminsUpgradeRequest;
use App\Models\User;
use App\Services\Currency\CurrencyService;
use App\Services\Subscription\TierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        protected MemoraSubscriptionService $subscriptionService,
        protected TierService $tierService,
        protected CurrencyService $currencyService
    ) {}

    /**
     * Get checkout options: enabled providers and their supported currencies
     */
    public function checkoutOptions(Request $request): JsonResponse
    {
        $providersConfig = config('payment.checkout_providers', []);
        $providers = [];
        $currencyInfo = config('currency.currencies', []);

        foreach ($providersConfig as $id => $config) {
            $providerConfig = config("payment.providers.{$id}", []);
            $secretKey = $providerConfig['secret_key'] ?? $providerConfig['client_secret'] ?? null;
            $publicKey = $providerConfig['public_key'] ?? $providerConfig['client_id'] ?? null;
            $isEnabled = $id === 'stripe'
                ? (! empty($secretKey) && ! empty($publicKey))
                : (! empty($providerConfig['test_mode']) || (! empty($secretKey) && ! empty($publicKey)));

            if (! $isEnabled) {
                continue;
            }

            $currencies = collect($config['currencies'] ?? [])->map(function ($code) use ($currencyInfo) {
                $info = $currencyInfo[strtoupper($code)] ?? [];

                return [
                    'code' => strtolower($code),
                    'symbol' => $info['symbol'] ?? $code,
                ];
            })->values()->toArray();

            $providers[] = [
                'id' => $id,
                'name' => ucfirst($id),
                'currencies' => $currencies,
            ];
        }

        return response()->json(['data' => ['providers' => $providers]]);
    }

    /**
     * Preview plan amount in USD only. Frontend does conversion using /pricing/currency-rates.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'tier' => 'required|string|in:starter,pro,studio,business,byo',
            'billing_cycle' => 'required|string|in:monthly,annual',
            'byo_addons' => 'nullable',
        ]);

        $byoAddons = $request->byo_addons;
        if (is_string($byoAddons)) {
            $decoded = json_decode($byoAddons, true);
            $byoAddons = is_array($decoded) ? $decoded : null;
        }

        $amountCents = $this->subscriptionService->getPlanAmountCents(
            $request->tier,
            $request->billing_cycle,
            $byoAddons
        );

        $data = [
            'amount_cents' => $amountCents,
            'amount_formatted' => $this->currencyService->format($amountCents, 'USD'),
            'base_currency' => 'usd',
        ];

        if ($request->tier === 'byo') {
            $config = MemoraByoConfig::getConfig();
            if ($config) {
                $baseCents = $request->billing_cycle === 'annual'
                    ? (int) $config->base_price_annual_cents
                    : (int) $config->base_price_monthly_cents;
                $data['base_amount_cents'] = $baseCents;
                $data['base_amount_formatted'] = $this->currencyService->format($baseCents, 'USD');
                if ($request->billing_cycle === 'annual') {
                    $data['base_original_cents'] = (int) $config->base_price_monthly_cents * 12;
                }
            }
        } else {
            $data['base_amount_cents'] = $amountCents;
            $data['base_amount_formatted'] = $this->currencyService->format($amountCents, 'USD');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Get order summary (Hostinger-style itemized breakdown)
     */
    public function orderSummary(Request $request): JsonResponse
    {
        $request->validate([
            'tier' => 'required|string|in:starter,pro,studio,business,byo',
            'billing_cycle' => 'required|string|in:monthly,annual',
            'currency' => 'required|string|in:usd,eur,gbp,ngn,zar,kes,ghs,jpy,cad,aud',
            'byo_addons' => 'nullable',
        ]);

        $byoAddons = $request->byo_addons;
        if (is_string($byoAddons)) {
            $decoded = json_decode($byoAddons, true);
            $byoAddons = is_array($decoded) ? $decoded : null;
        }

        $summary = $this->subscriptionService->getOrderSummary(
            $request->tier,
            $request->billing_cycle,
            $byoAddons
        );

        $currency = strtoupper($request->currency);
        $lineItems = [];
        foreach ($summary['line_items'] as $item) {
            $origCents = $this->currencyService->convertUsdCentsToTarget($item['original_cents'], $currency);
            $priceCents = $this->currencyService->convertUsdCentsToTarget($item['price_cents'], $currency);
            $lineItems[] = [
                'name' => $item['name'],
                'detail' => $item['detail'],
                'original_formatted' => $this->currencyService->format($origCents, $currency),
                'price_formatted' => $this->currencyService->format($priceCents, $currency),
                'original_cents' => $origCents,
                'price_cents' => $priceCents,
            ];
        }

        $subtotalOriginal = $this->currencyService->convertUsdCentsToTarget($summary['subtotal_original_cents'], $currency);
        $subtotal = $this->currencyService->convertUsdCentsToTarget($summary['subtotal_cents'], $currency);

        $vatRate = (float) config('pricing.vat_rate', 0);
        $vatCents = $vatRate > 0 ? (int) floor($subtotal * $vatRate) : 0;
        $totalCents = $subtotal + $vatCents;

        $data = [
            'line_items' => $lineItems,
            'subtotal_formatted' => $this->currencyService->format($subtotal, $currency),
            'subtotal_original_formatted' => $this->currencyService->format($subtotalOriginal, $currency),
            'subtotal_cents' => $subtotal,
            'subtotal_original_cents' => $subtotalOriginal,
            'vat_rate' => $vatRate,
            'vat_cents' => $vatCents,
            'vat_formatted' => $vatCents > 0 ? $this->currencyService->format($vatCents, $currency) : null,
            'total_cents' => $totalCents,
            'total_formatted' => $this->currencyService->format($totalCents, $currency),
            'has_discount' => $summary['has_discount'],
            'currency' => strtolower($currency),
        ];

        if ($currency !== 'USD') {
            $data['base_subtotal_formatted'] = $this->currencyService->format($summary['subtotal_cents'], 'USD');
            $data['base_subtotal_original_formatted'] = $summary['has_discount']
                ? $this->currencyService->format($summary['subtotal_original_cents'], 'USD')
                : null;
            $oneUsdCents = $this->currencyService->convertUsdCentsToTarget(100, $currency);
            $data['one_usd_equals'] = $this->currencyService->format($oneUsdCents, $currency);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Create checkout session for subscription
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'tier' => 'required|string|in:starter,pro,studio,business,byo',
            'billing_cycle' => 'required|string|in:monthly,annual',
            'payment_provider' => 'required|string|in:stripe,paypal,paystack,flutterwave',
            'currency' => 'required|string|in:usd,eur,gbp,ngn,zar,kes,ghs,jpy,cad,aud',
            'byo_addons' => 'nullable|array',
        ]);

        $user = Auth::user();

        try {
            $result = $this->subscriptionService->createCheckoutSession(
                $user,
                $request->tier,
                $request->billing_cycle,
                $request->payment_provider,
                $request->currency,
                $request->byo_addons
            );

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                    'subscription_checkout_started',
                    null,
                    'Subscription checkout started',
                    ['tier' => $request->tier, 'billing_cycle' => $request->billing_cycle, 'provider' => $request->payment_provider],
                    $user,
                    $request
                );
            } catch (\Throwable $logEx) {
                Log::warning('Failed to log subscription activity', ['error' => $logEx->getMessage()]);
            }

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create checkout session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete test-mode checkout (Stripe/Paystack/Flutterwave/PayPal test mode)
     */
    public function completeTestCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'payment_provider' => 'nullable|string|in:stripe,paystack,flutterwave,paypal',
        ]);

        $user = Auth::user();
        $completed = $this->subscriptionService->completeTestCheckout(
            $request->session_id,
            $user,
            $request->payment_provider
        );

        if (! $completed) {
            return response()->json([
                'error' => 'Invalid or expired test checkout session',
                'code' => 'INVALID_SESSION',
            ], 400);
        }

        return response()->json(['data' => ['success' => true]]);
    }

    /**
     * Create billing portal session
     */
    public function portal(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $result = $this->subscriptionService->createPortalSession($user);

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create portal session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current subscription status
     */
    public function status(Request $request): JsonResponse
    {
        $user = Auth::user();
        $subscription = $this->subscriptionService->getActiveSubscription($user);

        if ($subscription) {
            $effectiveEnd = $subscription->getEffectivePeriodEnd();
            if ($effectiveEnd && (! $subscription->current_period_end || $subscription->current_period_end->lte($subscription->current_period_start))) {
                $subscription->update(['current_period_end' => $effectiveEnd]);
            }
        }

        $data = [
            'has_subscription' => $subscription !== null,
            'subscription' => $subscription ? [
                'tier' => $subscription->tier,
                'billing_cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'payment_provider' => $subscription->payment_provider,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->getEffectivePeriodEnd(),
                'canceled_at' => $subscription->canceled_at,
                'on_grace_period' => $subscription->onGracePeriod(),
            ] : null,
            'current_tier' => $user->memora_tier ?? 'starter',
            'can_self_service_upgrade' => $this->subscriptionService->canSelfServiceUpgrade($user),
            'has_pending_upgrade_or_downgrade_request' => MemoraUpgradeRequest::where('user_uuid', $user->uuid)->where('status', 'pending')->exists()
                || MemoraDowngradeRequest::where('user_uuid', $user->uuid)->where('status', 'pending')->exists(),
        ];
        if ($user->memora_tier === 'byo') {
            $byoDisplay = $this->tierService->getByoPlanDisplay($user);
            $data['memora_features'] = $byoDisplay['features'];
            $data['memora_capabilities'] = $byoDisplay['capabilities'];
            $data['byo_addons_list'] = $byoDisplay['byo_addons_list'];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * List current user's upgrade and downgrade requests.
     */
    public function myPlanRequests(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = min((int) $request->query('limit', 20), 50);

        $upgradeRequests = MemoraUpgradeRequest::where('user_uuid', $user->uuid)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($req) => [
                'uuid' => $req->uuid,
                'type' => 'upgrade',
                'current_tier' => $req->current_tier,
                'target_tier' => $req->target_tier,
                'status' => $req->status,
                'requested_at' => $req->requested_at?->toIso8601String(),
                'completed_at' => $req->completed_at?->toIso8601String(),
            ]);

        $downgradeRequests = MemoraDowngradeRequest::where('user_uuid', $user->uuid)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($req) => [
                'uuid' => $req->uuid,
                'type' => 'downgrade',
                'current_tier' => $req->current_tier,
                'target_tier' => $req->target_tier,
                'status' => $req->status,
                'requested_at' => $req->requested_at?->toIso8601String(),
                'completed_at' => $req->completed_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'upgrade_requests' => $upgradeRequests,
                'downgrade_requests' => $downgradeRequests,
            ],
        ]);
    }

    /**
     * Get pending checkout URL for Plan Summary (admin-generated upgrade/downgrade).
     * Returns the first pending request with a checkout_url so user can proceed to payment.
     */
    public function pendingCheckout(Request $request): JsonResponse
    {
        $user = Auth::user();

        $upgrade = MemoraUpgradeRequest::where('user_uuid', $user->uuid)
            ->where('status', 'pending')
            ->whereNotNull('checkout_url')
            ->orderByDesc('created_at')
            ->first();

        if ($upgrade) {
            return response()->json([
                'data' => [
                    'checkout_url' => $upgrade->checkout_url,
                    'type' => 'upgrade',
                    'tier' => $upgrade->target_tier,
                    'billing_cycle' => null,
                ],
            ]);
        }

        $downgrade = MemoraDowngradeRequest::where('user_uuid', $user->uuid)
            ->where('status', 'pending')
            ->whereNotNull('checkout_url')
            ->orderByDesc('created_at')
            ->first();

        if ($downgrade) {
            return response()->json([
                'data' => [
                    'checkout_url' => $downgrade->checkout_url,
                    'type' => 'downgrade',
                    'tier' => $downgrade->target_tier,
                    'billing_cycle' => null,
                ],
            ]);
        }

        return response()->json(['data' => null], 404);
    }

    /**
     * Get Stripe publishable key
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'data' => [
                'publishable_key' => $this->subscriptionService->getPublishableKey(),
            ],
        ]);
    }

    /**
     * Check if user can downgrade to Starter (usage within limits)
     */
    public function canDowngrade(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validation = $this->subscriptionService->validateDowngradeToStarter($user);

        return response()->json([
            'data' => [
                'can_downgrade' => $validation['valid'],
                'errors' => $validation['errors'],
                'limits' => $validation['limits'],
                'usage' => $validation['usage'],
            ],
        ]);
    }

    /**
     * Cancel subscription (end-users cannot self-cancel; use request-downgrade flow)
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = Auth::user();
        $subscription = $this->subscriptionService->getActiveSubscription($user);

        if (! $subscription) {
            return response()->json([
                'error' => 'No active subscription found',
                'code' => 'NO_SUBSCRIPTION',
            ], 404);
        }

        return response()->json([
            'error' => 'Downgrades are not self-service. Request a downgrade from Plans & Pricing; we\'ll send you a link to confirm and then switch your plan.',
            'code' => 'DOWNGRADE_VIA_REQUEST',
        ], 403);
    }

    /**
     * Request a downgrade (creates request, notifies admins)
     */
    public function requestDowngrade(Request $request): JsonResponse
    {
        $user = Auth::user();
        $subscription = $this->subscriptionService->getActiveSubscription($user);

        if (! $subscription) {
            return response()->json([
                'error' => 'No active subscription found',
                'code' => 'NO_SUBSCRIPTION',
            ], 404);
        }

        $hasPendingDowngrade = MemoraDowngradeRequest::where('user_uuid', $user->uuid)
            ->where('status', 'pending')
            ->exists();
        $hasPendingUpgrade = MemoraUpgradeRequest::where('user_uuid', $user->uuid)
            ->where('status', 'pending')
            ->exists();
        if ($hasPendingDowngrade || $hasPendingUpgrade) {
            return response()->json([
                'error' => 'You already have a pending upgrade or downgrade request.',
                'code' => 'PENDING_REQUEST',
            ], 409);
        }

        $downgradeRequest = MemoraDowngradeRequest::create([
            'user_uuid' => $user->uuid,
            'current_tier' => $subscription->tier,
            'target_tier' => 'starter',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $adminUuids = Cache::remember('downgrade_admin_uuids', 600, function () {
            return User::whereIn('role', [\App\Enums\UserRoleEnum::ADMIN, \App\Enums\UserRoleEnum::SUPER_ADMIN])
                ->pluck('uuid')
                ->toArray();
        });

        if (! empty($adminUuids)) {
            NotifyAdminsDowngradeRequest::dispatchSync(
                $adminUuids,
                $user->first_name,
                $user->last_name,
                $user->email,
                $downgradeRequest->uuid,
                $subscription->tier
            );
        }

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'subscription_downgrade_requested',
                $downgradeRequest,
                'User requested subscription downgrade',
                ['current_tier' => $subscription->tier, 'target_tier' => 'starter'],
                $user,
                $request
            );
        } catch (\Throwable $logEx) {
            Log::warning('Failed to log subscription activity', ['error' => $logEx->getMessage()]);
        }

        return response()->json([
            'data' => [
                'request_uuid' => $downgradeRequest->uuid,
                'message' => 'Downgrade requested. Support will send you a link to confirm.',
            ],
        ], 201);
    }

    /**
     * Request an upgrade (creates request, notifies admins)
     */
    public function requestUpgrade(Request $request): JsonResponse
    {
        $request->validate(['target_tier' => 'required|string|in:pro,studio,business']);

        $user = Auth::user();
        $subscription = $this->subscriptionService->getActiveSubscription($user);
        $currentTier = $subscription?->tier ?? $user->memora_tier ?? null;

        $hasPendingUpgrade = MemoraUpgradeRequest::where('user_uuid', $user->uuid)
            ->where('status', 'pending')
            ->exists();
        $hasPendingDowngrade = MemoraDowngradeRequest::where('user_uuid', $user->uuid)
            ->where('status', 'pending')
            ->exists();
        if ($hasPendingUpgrade || $hasPendingDowngrade) {
            return response()->json([
                'error' => 'You already have a pending upgrade or downgrade request.',
                'code' => 'PENDING_REQUEST',
            ], 409);
        }

        $upgradeRequest = MemoraUpgradeRequest::create([
            'user_uuid' => $user->uuid,
            'current_tier' => $currentTier,
            'target_tier' => $request->target_tier,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $adminUuids = Cache::remember('upgrade_admin_uuids', 600, function () {
            return User::whereIn('role', [\App\Enums\UserRoleEnum::ADMIN, \App\Enums\UserRoleEnum::SUPER_ADMIN])
                ->pluck('uuid')
                ->toArray();
        });

        if (! empty($adminUuids)) {
            NotifyAdminsUpgradeRequest::dispatchSync(
                $adminUuids,
                $user->first_name,
                $user->last_name,
                $user->email,
                $upgradeRequest->uuid,
                $currentTier,
                $request->target_tier
            );
        }

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'subscription_upgrade_requested',
                $upgradeRequest,
                'User requested subscription upgrade',
                ['current_tier' => $currentTier, 'target_tier' => $request->target_tier],
                $user,
                $request
            );
        } catch (\Throwable $logEx) {
            Log::warning('Failed to log subscription activity', ['error' => $logEx->getMessage()]);
        }

        return response()->json([
            'data' => [
                'request_uuid' => $upgradeRequest->uuid,
                'message' => 'Upgrade requested. Support will send you a checkout link to complete the change.',
            ],
        ], 201);
    }

    /**
     * Get downgrade request by token (for confirm page)
     */
    public function downgradeByToken(Request $request): JsonResponse
    {
        $token = $request->query('token');
        if (! $token) {
            return response()->json(['error' => 'Token required'], 404);
        }

        $downgradeRequest = MemoraDowngradeRequest::where('confirm_token', $token)->first();
        if (! $downgradeRequest || $downgradeRequest->status !== 'pending') {
            return response()->json(['error' => 'Invalid or expired link'], 404);
        }

        if ($downgradeRequest->confirm_token_expires_at && $downgradeRequest->confirm_token_expires_at->isPast()) {
            return response()->json(['error' => 'Link has expired'], 404);
        }

        $user = $downgradeRequest->user;
        $currentPlan = $downgradeRequest->current_tier;

        return response()->json([
            'data' => [
                'target_tier' => $downgradeRequest->target_tier,
                'user_display_name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                'current_plan' => $currentPlan,
                'expires_at' => $downgradeRequest->confirm_token_expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Confirm downgrade (token flow for Starter)
     */
    public function confirmDowngrade(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $user = Auth::user();
        $downgradeRequest = MemoraDowngradeRequest::where('confirm_token', $request->token)->first();

        if (! $downgradeRequest || $downgradeRequest->user_uuid !== $user->uuid) {
            return response()->json(['error' => 'Invalid or expired link'], 404);
        }

        if ($downgradeRequest->status === 'completed') {
            return response()->json(['data' => ['message' => 'Downgrade already completed']]);
        }

        if ($downgradeRequest->confirm_token_expires_at && $downgradeRequest->confirm_token_expires_at->isPast()) {
            return response()->json(['error' => 'Link has expired'], 404);
        }

        if ($downgradeRequest->target_tier !== 'starter') {
            return response()->json(['error' => 'Invalid request'], 422);
        }

        $this->subscriptionService->cancelSubscriptionDirectly($user);

        $downgradeRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
            'confirm_token' => null,
            'confirm_token_expires_at' => null,
        ]);

        Log::info('Downgrade confirmed', [
            'user_uuid' => $user->uuid,
            'request_uuid' => $downgradeRequest->uuid,
            'action' => 'confirm_downgrade',
        ]);

        try {
            app(\App\Services\ActivityLog\ActivityLogService::class)->log(
                'subscription_downgrade_confirmed',
                $downgradeRequest,
                'User confirmed subscription downgrade to Starter',
                ['target_tier' => 'starter'],
                $user,
                $request
            );
        } catch (\Throwable $logEx) {
            Log::warning('Failed to log subscription activity', ['error' => $logEx->getMessage()]);
        }

        return response()->json([
            'data' => ['message' => 'You have been downgraded to Starter.'],
        ]);
    }

    /**
     * Get subscription history
     */
    public function history(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = min((int) $request->get('limit', 20), 100);

        $history = $this->subscriptionService->getHistory($user, $limit);

        return response()->json([
            'data' => $history,
        ]);
    }

    /**
     * Get detailed usage analytics
     */
    public function usage(Request $request): JsonResponse
    {
        $user = Auth::user();
        $userUuid = $user->uuid;

        // Get tier limits (storage from getStorageLimit so BYO includes addon bytes)
        $tierConfig = $this->tierService->getTierConfig($user);
        $storageLimit = $this->tierService->getStorageLimit($user) ?? $tierConfig['storage_bytes'] ?? (5 * 1024 * 1024 * 1024);
        $projectLimit = $tierConfig['project_limit'] ?? null;
        $collectionLimit = $tierConfig['collection_limit'] ?? null;

        // Storage breakdown: media (selections/proofing/collections) vs raw files
        $mediaStorage = (int) DB::table('memora_media')
            ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
            ->join('user_files', 'memora_media.user_file_uuid', '=', 'user_files.uuid')
            ->where('memora_media.user_uuid', $userUuid)
            ->whereNull('memora_media_sets.raw_file_uuid')
            ->whereNull('memora_media.deleted_at')
            ->whereNull('memora_media_sets.deleted_at')
            ->whereNull('user_files.deleted_at')
            ->sum('user_files.size');
        // Raw file storage = sum of user_files.size for media in raw file media sets
        $rawFileStorage = (int) DB::table('memora_media')
            ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
            ->join('memora_raw_files', 'memora_media_sets.raw_file_uuid', '=', 'memora_raw_files.uuid')
            ->join('user_files', 'memora_media.user_file_uuid', '=', 'user_files.uuid')
            ->where('memora_raw_files.user_uuid', $userUuid)
            ->whereNotNull('memora_media_sets.raw_file_uuid')
            ->whereNull('memora_media.deleted_at')
            ->whereNull('memora_media_sets.deleted_at')
            ->whereNull('memora_raw_files.deleted_at')
            ->whereNull('user_files.deleted_at')
            ->sum('user_files.size');
        $totalStorage = $mediaStorage + $rawFileStorage;

        // Resource counts
        $projectCount = MemoraProject::where('user_uuid', $userUuid)->count();
        $collectionCount = MemoraCollection::where('user_uuid', $userUuid)->count();
        $mediaCount = MemoraMedia::where('user_uuid', $userUuid)->count();
        $selectionCount = MemoraSelection::where('user_uuid', $userUuid)->count();
        $proofingCount = MemoraProofing::where('user_uuid', $userUuid)->count();
        $rawFileCount = MemoraRawFile::where('user_uuid', $userUuid)->count();

        // Monthly usage trend (last 6 months)
        $usageTrend = DB::table('memora_media')
            ->leftJoin('user_files', function ($join) {
                $join->on('memora_media.user_file_uuid', '=', 'user_files.uuid')
                    ->whereNull('user_files.deleted_at');
            })
            ->where('memora_media.user_uuid', $userUuid)
            ->whereNull('memora_media.deleted_at')
            ->where('memora_media.created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(memora_media.created_at, '%Y-%m') as month, COUNT(*) as media_count, COALESCE(SUM(user_files.size), 0) as storage_bytes")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'media_count' => (int) $row->media_count,
                'storage_bytes' => (int) $row->storage_bytes,
            ])
            ->toArray();

        return response()->json([
            'data' => [
                'tier' => $user->memora_tier ?? 'starter',
                'storage' => [
                    'used_bytes' => $totalStorage,
                    'limit_bytes' => $storageLimit,
                    'percentage' => $storageLimit > 0 ? round(($totalStorage / $storageLimit) * 100, 1) : 0,
                    'breakdown' => [
                        'media' => $mediaStorage,
                        'raw_files' => $rawFileStorage,
                    ],
                ],
                'resources' => [
                    'projects' => [
                        'count' => $projectCount,
                        'limit' => $projectLimit,
                    ],
                    'collections' => [
                        'count' => $collectionCount,
                        'limit' => $collectionLimit,
                    ],
                    'media' => $mediaCount,
                    'selections' => $selectionCount,
                    'proofing' => $proofingCount,
                    'raw_files' => $rawFileCount,
                ],
                'usage_trend' => $usageTrend,
            ],
        ]);
    }
}
