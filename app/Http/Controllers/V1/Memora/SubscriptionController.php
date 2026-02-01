<?php

namespace App\Http\Controllers\V1\Memora;

use App\Services\Currency\CurrencyService;
use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraRawFile;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Models\MemoraSubscription;
use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Services\Subscription\TierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $isEnabled = ! empty($providerConfig['test_mode']) || (! empty($secretKey) && ! empty($publicKey));

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
     * Preview converted amount for a plan
     */
    public function preview(Request $request): JsonResponse
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

        $amountCents = $this->subscriptionService->getPlanAmountCents(
            $request->tier,
            $request->billing_cycle,
            $byoAddons
        );

        $currency = strtoupper($request->currency);
        $convertedCents = $this->currencyService->convert($amountCents, 'USD', $currency);
        $formatted = $this->currencyService->format($convertedCents, $currency);

        $data = [
            'amount_cents' => $convertedCents,
            'amount_formatted' => $formatted,
            'base_amount_cents' => $amountCents,
            'base_amount_formatted' => $this->currencyService->format($amountCents, 'USD'),
            'base_currency' => 'usd',
            'currency' => strtolower($currency),
        ];

        if ($currency !== 'USD') {
            $oneUsdCents = $this->currencyService->convert(100, 'USD', $currency);
            $data['one_usd_equals'] = $this->currencyService->format($oneUsdCents, $currency);
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
            $origCents = $this->currencyService->convert($item['original_cents'], 'USD', $currency);
            $priceCents = $this->currencyService->convert($item['price_cents'], 'USD', $currency);
            $lineItems[] = [
                'name' => $item['name'],
                'detail' => $item['detail'],
                'original_formatted' => $this->currencyService->format($origCents, $currency),
                'price_formatted' => $this->currencyService->format($priceCents, $currency),
                'original_cents' => $origCents,
                'price_cents' => $priceCents,
            ];
        }

        $subtotalOriginal = $this->currencyService->convert($summary['subtotal_original_cents'], 'USD', $currency);
        $subtotal = $this->currencyService->convert($summary['subtotal_cents'], 'USD', $currency);

        $data = [
            'line_items' => $lineItems,
            'subtotal_formatted' => $this->currencyService->format($subtotal, $currency),
            'subtotal_original_formatted' => $this->currencyService->format($subtotalOriginal, $currency),
            'subtotal_cents' => $subtotal,
            'subtotal_original_cents' => $subtotalOriginal,
            'has_discount' => $summary['has_discount'],
            'currency' => strtolower($currency),
        ];

        if ($currency !== 'USD') {
            $data['base_subtotal_formatted'] = $this->currencyService->format($summary['subtotal_cents'], 'USD');
            $data['base_subtotal_original_formatted'] = $summary['has_discount']
                ? $this->currencyService->format($summary['subtotal_original_cents'], 'USD')
                : null;
            $oneUsdCents = $this->currencyService->convert(100, 'USD', $currency);
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

        return response()->json([
            'data' => [
                'has_subscription' => $subscription !== null,
                'subscription' => $subscription ? [
                    'tier' => $subscription->tier,
                    'billing_cycle' => $subscription->billing_cycle,
                    'status' => $subscription->status,
                    'amount' => $subscription->amount,
                    'currency' => $subscription->currency,
                    'payment_provider' => $subscription->payment_provider,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'canceled_at' => $subscription->canceled_at,
                    'on_grace_period' => $subscription->onGracePeriod(),
                ] : null,
                'current_tier' => $user->memora_tier ?? 'starter',
            ],
        ]);
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
     * Cancel subscription (marks for cancellation at period end)
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

        $validation = $this->subscriptionService->validateDowngradeToStarter($user);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Cannot downgrade: your usage exceeds Starter plan limits.',
                'code' => 'USAGE_EXCEEDS_LIMITS',
                'errors' => $validation['errors'],
                'limits' => $validation['limits'],
                'usage' => $validation['usage'],
            ], 422);
        }

        $portalUrl = config('app.frontend_url') . '/memora/pricing';

        try {
            $provider = $subscription->payment_provider ?? 'stripe';
            $useDirectCancel = $provider === 'paypal'
                || ($provider === 'stripe' && config('payment.providers.stripe.test_mode'));

            if ($useDirectCancel) {
                $this->subscriptionService->cancelSubscriptionDirectly($user);

                return response()->json([
                    'data' => [
                        'message' => 'Subscription cancelled. You have been downgraded to Starter.',
                        'portal_url' => $portalUrl,
                    ],
                ]);
            }

            $result = $this->subscriptionService->createPortalSession($user);

            return response()->json([
                'data' => [
                    'message' => 'Redirecting to billing portal to manage subscription',
                    'portal_url' => $result['portal_url'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process cancellation',
                'message' => $e->getMessage(),
            ], 500);
        }
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

        // Get tier limits
        $tierConfig = $this->tierService->getTierConfig($user);
        $storageLimit = $tierConfig['storage_bytes'] ?? (5 * 1024 * 1024 * 1024);
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
