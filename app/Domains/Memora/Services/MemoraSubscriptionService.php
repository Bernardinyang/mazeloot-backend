<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraByoAddon;
use App\Domains\Memora\Models\MemoraByoConfig;
use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraPricingTier;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraSubscription;
use App\Domains\Memora\Models\MemoraSubscriptionHistory;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\SubscriptionRenewedNotification;
use App\Services\Currency\CurrencyService;
use App\Services\Notification\NotificationService;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\Providers\FlutterwaveProvider;
use App\Services\Payment\Providers\PayPalProvider;
use App\Services\Payment\Providers\PaystackProvider;
use App\Services\Payment\Providers\StripeProvider;
use App\Services\Storage\UserStorageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemoraSubscriptionService
{
    public function __construct(
        protected StripeProvider $stripe,
        protected NotificationService $notificationService,
        protected EmailNotificationService $emailNotificationService,
        protected CurrencyService $currencyService,
        protected UserStorageService $storageService
    ) {}

    protected function getSubscriptionProvider(string $name): SubscriptionProviderInterface
    {
        if ($name === 'stripe') {
            return $this->stripe;
        }

        return match ($name) {
            'paystack' => app()->make(PaystackProvider::class),
            'flutterwave' => app()->make(FlutterwaveProvider::class),
            'paypal' => app()->make(PayPalProvider::class),
            default => throw new \RuntimeException("Unknown provider: {$name}"),
        };
    }

    /**
     * Create a checkout session for a pricing tier
     */
    public function createCheckoutSession(User $user, string $tier, string $billingCycle, string $paymentProvider, string $currency, ?array $byoAddons = null): array
    {
        if ($paymentProvider === 'stripe') {
            return $this->createStripeCheckoutSession($user, $tier, $billingCycle, $currency, $byoAddons);
        }

        if ($paymentProvider === 'paystack') {
            return $this->createPaystackCheckoutSession($user, $tier, $billingCycle, $currency, $byoAddons);
        }

        if ($paymentProvider === 'flutterwave') {
            return $this->createFlutterwaveCheckoutSession($user, $tier, $billingCycle, $currency, $byoAddons);
        }

        if ($paymentProvider === 'paypal') {
            $paypalConfig = config('payment.providers.paypal', []);
            if (empty($paypalConfig['client_id']) || empty($paypalConfig['client_secret'])) {
                throw new \RuntimeException('PayPal is not configured. For test mode set PAYPAL_TEST_CLIENT_ID and PAYPAL_TEST_CLIENT_SECRET; for live set PAYPAL_LIVE_CLIENT_ID and PAYPAL_LIVE_CLIENT_SECRET.');
            }

            return $this->createPayPalCheckoutSession($user, $tier, $billingCycle, $currency, $byoAddons);
        }

        throw new \RuntimeException("Unknown payment provider: {$paymentProvider}");
    }

    protected function createPayPalCheckoutSession(User $user, string $tier, string $billingCycle, string $currency, ?array $byoAddons): array
    {
        if ($tier === 'byo') {
            throw new \RuntimeException('Build Your Own is not yet supported for PayPal. Please use Stripe or another provider.');
        }

        $paypal = app()->make(PayPalProvider::class);
        $currencyLower = strtolower($currency);
        $currencyUpper = strtoupper($currency);

        $subtotalUsdCents = $this->calculateAmount($tier, $billingCycle, $byoAddons);
        if ($subtotalUsdCents < 1) {
            throw new \InvalidArgumentException('Pricing not configured for the selected plan. Please contact support.');
        }
        $vatUsdCents = $this->vatCents($subtotalUsdCents);
        $totalUsdCents = $subtotalUsdCents + $vatUsdCents;
        $amountSubunits = $currencyLower === 'usd'
            ? $totalUsdCents
            : $this->currencyService->convert($totalUsdCents, 'USD', $currencyUpper);
        if ($amountSubunits < 50) {
            throw new \InvalidArgumentException('Amount is below the minimum required for '.$currencyUpper.'.');
        }

        $mode = config('payment.providers.paypal.test_mode', true) ? 'test' : 'live';
        $planName = 'Memora '.ucfirst($tier).' '.($billingCycle === 'annual' ? 'Annual' : 'Monthly');
        $planCacheKey = "paypal_plan:{$mode}:{$tier}:{$billingCycle}:{$currencyLower}:{$amountSubunits}";
        $planId = Cache::get($planCacheKey);
        if (! $planId) {
            $productId = $paypal->createProduct();
            $planId = $paypal->createPlan($productId, $planName, $amountSubunits, $currencyUpper, $billingCycle);
            Cache::put($planCacheKey, $planId, 86400 * 7);
        }

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $returnUrl = $frontendUrl.'/memora/pricing/status?provider=paypal';
        $cancelUrl = $frontendUrl.'/memora/pricing';

        $token = Str::random(32);
        $metadata = [
            'user_uuid' => $user->uuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
            'byo_addons' => $byoAddons,
        ];
        Cache::put("paypal_pending:{$token}", $metadata, 3600);

        try {
            $result = $paypal->initializeSubscription([
                'plan_id' => $planId,
                'custom_id' => $token,
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'subscriber_email' => $user->email,
            ]);
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'plan') !== false || stripos($e->getMessage(), 'not found') !== false) {
                Cache::forget($planCacheKey);
                $productId = $paypal->createProduct();
                $planId = $paypal->createPlan($productId, $planName, $amountSubunits, $currencyUpper, $billingCycle);
                Cache::put($planCacheKey, $planId, 86400 * 7);
                $result = $paypal->initializeSubscription([
                    'plan_id' => $planId,
                    'custom_id' => $token,
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'subscriber_email' => $user->email,
                ]);
            } else {
                throw $e;
            }
        }

        return [
            'checkout_url' => $result['checkout_url'] ?? $result['authorization_url'],
            'session_id' => $result['subscription_id'] ?? $result['reference'],
        ];
    }

    protected function createFlutterwaveCheckoutSession(User $user, string $tier, string $billingCycle, string $currency, ?array $byoAddons): array
    {
        $flutterwave = app()->make(FlutterwaveProvider::class);
        $currencyLower = strtolower($currency);
        $currencyUpper = strtoupper($currency);

        $subtotalUsdCents = $this->calculateAmount($tier, $billingCycle, $byoAddons);
        if ($subtotalUsdCents < 1) {
            throw new \InvalidArgumentException('Pricing not configured for the selected plan. Please contact support.');
        }
        $vatUsdCents = $this->vatCents($subtotalUsdCents);
        $totalUsdCents = $subtotalUsdCents + $vatUsdCents;
        $amountSubunits = $currencyLower === 'usd'
            ? $totalUsdCents
            : $this->currencyService->convert($totalUsdCents, 'USD', $currencyUpper);
        $minSubunits = $currencyLower === 'ngn' ? 10000 : 50;
        if ($amountSubunits < $minSubunits) {
            throw new \InvalidArgumentException('Amount is below the minimum required for '.$currencyUpper.'.');
        }

        $txRef = 'memora_'.Str::random(16);
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $redirectUrl = $frontendUrl.'/memora/pricing/status?provider=flutterwave';

        $metadata = [
            'user_uuid' => $user->uuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
        ];
        if ($byoAddons) {
            $metadata['byo_addons'] = json_encode($byoAddons);
        }

        $result = $flutterwave->charge([
            'amount' => $amountSubunits,
            'currency' => $currencyUpper,
            'email' => $user->email,
            'customer_name' => $user->name ?? 'Customer',
            'reference' => $txRef,
            'tx_ref' => $txRef,
            'callback_url' => $redirectUrl,
            'redirect_url' => $redirectUrl,
            'metadata' => $metadata,
        ]);

        if ($result->status === 'failed') {
            throw new \RuntimeException($result->errorMessage ?? 'Failed to initialize Flutterwave payment');
        }

        $link = $result->metadata['link'] ?? '';
        if ($link === '') {
            throw new \RuntimeException('Flutterwave did not return a checkout link');
        }

        Cache::put('flutterwave_pending:'.$txRef, [
            'user_uuid' => $user->uuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
            'byo_addons' => $byoAddons,
            'currency' => $currencyLower,
        ], 3600);

        return [
            'checkout_url' => $link,
            'session_id' => $txRef,
        ];
    }

    protected function createPaystackCheckoutSession(User $user, string $tier, string $billingCycle, string $currency, ?array $byoAddons): array
    {
        $paystack = app()->make(PaystackProvider::class);
        $currencyLower = strtolower($currency);
        $currencyUpper = strtoupper($currency);

        $subtotalUsdCents = $this->calculateAmount($tier, $billingCycle, $byoAddons);
        if ($subtotalUsdCents < 1) {
            throw new \InvalidArgumentException('Pricing not configured for the selected plan. Please contact support.');
        }
        $vatUsdCents = $this->vatCents($subtotalUsdCents);
        $totalUsdCents = $subtotalUsdCents + $vatUsdCents;
        $amountSubunits = $currencyLower === 'usd'
            ? $totalUsdCents
            : $this->currencyService->convert($totalUsdCents, 'USD', $currencyUpper);
        $minSubunits = $currencyLower === 'ngn' ? 10000 : 50;
        if ($amountSubunits < $minSubunits) {
            throw new \InvalidArgumentException('Amount is below the minimum required for '.$currencyUpper.'.');
        }

        $paystackMode = config('payment.providers.paystack.test_mode', true) ? 'test' : 'live';
        $planCacheKey = 'paystack_plan:'.$paystackMode.':'.$tier.':'.$billingCycle.':'.$currencyLower.':'.$amountSubunits;
        $planCode = Cache::get($planCacheKey);
        if (! $planCode) {
            $planName = 'Memora '.($tier === 'byo' ? 'Build Your Own' : ucfirst($tier)).' '.($billingCycle === 'annual' ? 'Annual' : 'Monthly');
            $planCode = $paystack->createPlan($planName, $amountSubunits, $billingCycle, $currencyUpper);
            Cache::put($planCacheKey, $planCode, 86400);
        }

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $callbackUrl = $frontendUrl.'/memora/pricing/status?provider=paystack';

        $metadata = [
            'user_uuid' => $user->uuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
        ];
        if ($byoAddons) {
            $metadata['byo_addons'] = json_encode($byoAddons);
        }

        try {
            $result = $paystack->initializeSubscriptionTransaction($user->email, $planCode, $amountSubunits, $currencyUpper, $callbackUrl, $metadata);
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Plan not found') !== false) {
                Cache::forget($planCacheKey);
                $planName = 'Memora '.($tier === 'byo' ? 'Build Your Own' : ucfirst($tier)).' '.($billingCycle === 'annual' ? 'Annual' : 'Monthly');
                $planCode = $paystack->createPlan($planName, $amountSubunits, $billingCycle, $currencyUpper);
                Cache::put($planCacheKey, $planCode, 86400);
                $result = $paystack->initializeSubscriptionTransaction($user->email, $planCode, $amountSubunits, $currencyUpper, $callbackUrl, $metadata);
            } else {
                throw $e;
            }
        }

        Cache::put('paystack_pending:'.$user->email, [
            'reference' => $result['reference'],
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
            'byo_addons' => $byoAddons,
            'user_uuid' => $user->uuid,
        ], 3600);

        return [
            'checkout_url' => $result['authorization_url'],
            'session_id' => $result['reference'],
        ];
    }

    protected function createStripeCheckoutSession(User $user, string $tier, string $billingCycle, string $currency, ?array $byoAddons): array
    {
        $customer = $this->stripe->getOrCreateCustomer(
            $user->email,
            $user->stripe_customer_id,
            ['user_uuid' => $user->uuid]
        );

        if (! $user->stripe_customer_id) {
            $user->update(['stripe_customer_id' => $customer->id]);
        }

        $lineItems = $this->buildLineItems($tier, $billingCycle, strtolower($currency), $byoAddons);
        $summary = $this->getOrderSummary($tier, $billingCycle, $byoAddons);
        $subtotalCents = $summary['subtotal_cents'];
        $currencyUpper = strtoupper($currency);
        $subtotalInCurrency = $currency === 'usd' ? $subtotalCents : $this->currencyService->convert($subtotalCents, 'USD', $currencyUpper);
        $vatRate = (float) config('pricing.vat_rate', 0);
        $vatInCurrency = $this->vatCents((int) $subtotalInCurrency);
        if ($vatInCurrency > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'VAT ('.round($vatRate * 100, 2).'%)',
                    ],
                    'unit_amount' => $vatInCurrency,
                    'recurring' => [
                        'interval' => $billingCycle === 'annual' ? 'year' : 'month',
                    ],
                ],
                'quantity' => 1,
            ];
        }
        $metadata = [
            'user_uuid' => $user->uuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
        ];
        if ($byoAddons) {
            $metadata['byo_addons'] = json_encode($byoAddons);
        }

        $session = $this->stripe->createCheckoutSession([
            'customer' => $customer->id,
            'mode' => 'subscription',
            'line_items' => $lineItems,
            'success_url' => config('app.frontend_url').'/memora/pricing/status?session_id={CHECKOUT_SESSION_ID}&provider=stripe',
            'cancel_url' => config('app.frontend_url').'/memora/pricing',
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
        ]);

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    protected function createTestCheckoutSession(User $user, string $tier, string $billingCycle, string $paymentProvider, string $currency, ?array $byoAddons): array
    {
        $sessionId = 'cs_'.$paymentProvider.'_test_'.Str::random(24);
        $metadata = [
            'user_uuid' => $user->uuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
        ];
        if ($byoAddons) {
            $metadata['byo_addons'] = json_encode($byoAddons);
        }
        Cache::put("test_checkout:{$paymentProvider}:{$sessionId}", $metadata, 3600);

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $checkoutUrl = "{$frontendUrl}/subscription/success?test=1&provider={$paymentProvider}&session_id={$sessionId}";

        return [
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Get plan amount in cents (base currency USD)
     */
    public function getPlanAmountCents(string $tier, string $billingCycle, ?array $byoAddons = null): int
    {
        return $this->calculateAmount($tier, $billingCycle, $byoAddons);
    }

    /**
     * Get order summary with line items for display (Hostinger-style)
     *
     * @return array{line_items: array, subtotal_cents: int, subtotal_original_cents: int, has_discount: bool}
     */
    public function getOrderSummary(string $tier, string $billingCycle, ?array $byoAddons = null): array
    {
        $lineItems = [];
        $subtotalOriginal = 0;
        $subtotal = 0;

        if ($tier === 'byo') {
            $config = MemoraByoConfig::getConfig();
            if ($config) {
                $baseMonthly = (int) $config->base_price_monthly_cents;
                $baseAnnual = (int) $config->base_price_annual_cents;
                $basePrice = $billingCycle === 'annual' ? $baseAnnual : $baseMonthly;
                $baseOriginal = $baseMonthly * 12;
                $lineItems[] = [
                    'name' => 'Build Your Own (Base)',
                    'detail' => $billingCycle === 'annual' ? '12-month plan' : 'Monthly',
                    'original_cents' => $billingCycle === 'annual' ? $baseOriginal : $baseMonthly,
                    'price_cents' => $basePrice,
                ];
                $subtotal += $basePrice;
                $subtotalOriginal += $billingCycle === 'annual' ? $baseOriginal : $baseMonthly;
            }
            if ($byoAddons) {
                $addons = MemoraByoAddon::whereIn('slug', array_keys($byoAddons))->get();
                foreach ($addons as $addon) {
                    $qty = (int) ($byoAddons[$addon->slug] ?? 1);
                    if ($qty <= 0) {
                        continue;
                    }
                    $addonMonthly = (int) $addon->price_monthly_cents;
                    $addonAnnual = (int) $addon->price_annual_cents;
                    $addonPrice = $billingCycle === 'annual' ? $addonAnnual * $qty : $addonMonthly * $qty;
                    $addonOriginal = $billingCycle === 'annual' ? $addonMonthly * 12 * $qty : $addonMonthly * $qty;
                    $lineItems[] = [
                        'name' => $addon->label,
                        'detail' => $qty > 1 ? "×{$qty}" : null,
                        'original_cents' => $addonOriginal,
                        'price_cents' => $addonPrice,
                    ];
                    $subtotal += $addonPrice;
                    $subtotalOriginal += $addonOriginal;
                }
            }
        } else {
            $tierData = MemoraPricingTier::getBySlug($tier);
            if ($tierData) {
                $monthly = (int) $tierData->price_monthly_cents;
                $annual = (int) $tierData->price_annual_cents;
                $price = $billingCycle === 'annual' ? $annual : $monthly;
                $original = $billingCycle === 'annual' ? $monthly * 12 : $monthly;
                $lineItems[] = [
                    'name' => $tierData->name,
                    'detail' => $billingCycle === 'annual' ? '12-month plan' : 'Monthly',
                    'original_cents' => $original,
                    'price_cents' => $price,
                ];
                $subtotal = $price;
                $subtotalOriginal = $original;
            }
        }

        $hasDiscount = $billingCycle === 'annual' && $subtotal < $subtotalOriginal;

        return [
            'line_items' => $lineItems,
            'subtotal_cents' => $subtotal,
            'subtotal_original_cents' => $subtotalOriginal,
            'has_discount' => $hasDiscount,
        ];
    }

    /**
     * Complete test-mode checkout (creates subscription from cached metadata).
     * Supports stripe (legacy key or provider key), paystack, flutterwave, paypal via optional $paymentProvider.
     */
    public function completeTestCheckout(string $sessionId, User $user, ?string $paymentProvider = null): bool
    {
        $metadata = null;
        $provider = $paymentProvider;

        if ($provider) {
            $metadata = Cache::pull("test_checkout:{$provider}:{$sessionId}");
        } else {
            $metadata = Cache::pull("test_checkout:{$sessionId}");
            if ($metadata && str_starts_with($sessionId, 'cs_test_')) {
                $provider = 'stripe';
            }
            if (! $metadata) {
                $metadata = Cache::pull("test_checkout:stripe:{$sessionId}");
                $provider = 'stripe';
            }
        }

        if (! $metadata) {
            return false;
        }

        $provider = $provider ?? 'stripe';
        $stripeTestMode = config('payment.providers.stripe.test_mode') ?? false;
        $otherTestMode = in_array($provider, ['paystack', 'flutterwave', 'paypal']);
        if (! $stripeTestMode && ! $otherTestMode) {
            return false;
        }

        $userUuid = $metadata['user_uuid'] ?? null;
        if (! $userUuid || $userUuid !== $user->uuid) {
            return false;
        }

        $tier = $metadata['tier'] ?? 'starter';
        $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
        $byoAddons = $this->normalizeByoAddons($metadata['byo_addons'] ?? null);
        $subtotalUsdCents = $this->calculateAmount($tier, $billingCycle, $byoAddons);
        $totalUsdCents = $subtotalUsdCents + $this->vatCents($subtotalUsdCents);
        $subscriptionId = 'sub_'.$provider.'_test_'.Str::random(14);
        $customerId = $user->stripe_customer_id ?: 'cus_'.$provider.'_test_'.Str::random(14);

        if (! $user->stripe_customer_id) {
            $user->update(['stripe_customer_id' => $customerId]);
        }

        $existingSubscription = $this->getActiveSubscription($user);
        if ($existingSubscription) {
            $existingSubscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        }

        MemoraSubscription::create([
            'uuid' => Str::uuid(),
            'user_uuid' => $user->uuid,
            'payment_provider' => $provider,
            'stripe_subscription_id' => $subscriptionId,
            'stripe_customer_id' => $customerId,
            'stripe_price_id' => 'price_test',
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
            'status' => 'active',
            'amount' => $totalUsdCents,
            'currency' => 'usd',
            'current_period_start' => now(),
            'current_period_end' => $billingCycle === 'annual' ? now()->addYear() : now()->addMonth(),
            'metadata' => $byoAddons ? ['byo_addons' => $byoAddons] : null,
        ]);

        $previousTier = $user->memora_tier ?? 'starter';
        $historyMeta = ['test_mode' => true];
        if ($tier === 'byo' && $byoAddons) {
            $historyMeta['byo_addons'] = $byoAddons;
        }
        MemoraSubscriptionHistory::record(
            $user->uuid,
            $previousTier === 'starter' ? 'created' : 'upgraded',
            $previousTier,
            $tier,
            $billingCycle,
            $totalUsdCents,
            $provider,
            $subscriptionId,
            $historyMeta,
            null,
            $metadata['currency'] ?? 'usd'
        );

        $user->update(['memora_tier' => $tier]);

        $this->notifySubscriptionActivated($user, $tier, $billingCycle);

        return true;
    }

    /**
     * Send in-app + email notification for subscription activated/upgraded
     */
    protected function notifySubscriptionActivated(User $user, string $tier, string $billingCycle): void
    {
        $tierLabel = $tier === 'byo' ? 'Build Your Own' : ucfirst($tier);
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'subscription_activated',
            'Subscription Activated',
            "Your {$tierLabel} plan is now active.",
            null,
            null,
            config('app.frontend_url').'/memora/usage',
            ['tier' => $tier, 'billing_cycle' => $billingCycle]
        );

        if ($this->emailNotificationService->isEnabledForUser($user->uuid, 'subscription_activated')) {
            $user->notify(new SubscriptionActivatedNotification($tier, $billingCycle));
        }
    }

    /**
     * Send in-app + email notification for subscription cancelled
     */
    protected function notifySubscriptionCancelled(User $user, ?string $previousTier, ?string $periodEnd): void
    {
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'subscription_cancelled',
            'Subscription Cancelled',
            'Your subscription has been cancelled. You have been downgraded to Starter.',
            null,
            null,
            config('app.frontend_url').'/memora/pricing',
            ['previous_tier' => $previousTier],
            ['priority' => 'HIGH']
        );

        if ($this->emailNotificationService->isEnabledForUser($user->uuid, 'subscription_cancelled')) {
            $user->notify(new SubscriptionCancelledNotification($previousTier, $periodEnd));
        }
    }

    /**
     * Send in-app + email notification for subscription renewed
     */
    public function notifySubscriptionRenewed(User $user, string $tier, string $billingCycle, ?string $periodEnd): void
    {
        $tierLabel = $tier === 'byo' ? 'Build Your Own' : ucfirst($tier);
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'subscription_renewed',
            'Subscription Renewed',
            "Your {$tierLabel} plan has been renewed.",
            null,
            null,
            config('app.frontend_url').'/memora/usage',
            ['tier' => $tier, 'billing_cycle' => $billingCycle]
        );

        if ($this->emailNotificationService->isEnabledForUser($user->uuid, 'subscription_renewed')) {
            $user->notify(new SubscriptionRenewedNotification($tier, $billingCycle, $periodEnd));
        }
    }

    /**
     * Send in-app + email notification for payment failed
     */
    public function notifyPaymentFailed(User $user, ?string $reason = null): void
    {
        $this->notificationService->create(
            $user->uuid,
            'memora',
            'payment_failed',
            'Payment Failed',
            'We could not process your payment. Please update your payment method.',
            null,
            null,
            config('app.frontend_url').'/memora/pricing',
            ['reason' => $reason],
            ['priority' => 'HIGH']
        );

        $user->notify(new PaymentFailedNotification($reason));
    }

    /**
     * Create billing portal session (Stripe or Paystack manage link)
     */
    public function createPortalSession(User $user): array
    {
        $subscription = $this->getActiveSubscription($user);
        if (! $subscription) {
            throw new \RuntimeException('No active subscription. Upgrade to a paid plan to manage your subscription.');
        }

        if ($subscription->payment_provider === 'paystack') {
            $paystack = app()->make(PaystackProvider::class);
            $portalUrl = $paystack->getManageSubscriptionLink($subscription->stripe_subscription_id);

            return ['portal_url' => $portalUrl];
        }

        if ($subscription->payment_provider === 'paypal') {
            throw new \RuntimeException('PayPal does not offer a billing portal. Manage your subscription at paypal.com or cancel from account settings.');
        }

        if ($subscription->payment_provider !== 'stripe') {
            throw new \RuntimeException('Manage subscription is only available for Stripe or Paystack. Cancel from account settings.');
        }

        $customerId = $user->stripe_customer_id ?? $subscription->stripe_customer_id;
        if ($customerId) {
            $user->update(['stripe_customer_id' => $customerId]);
        }
        if (! $customerId) {
            throw new \RuntimeException('No active subscription. Upgrade to a paid plan to manage your subscription.');
        }

        $session = $this->stripe->createPortalSession(
            $customerId,
            config('app.frontend_url').'/memora/pricing'
        );

        return [
            'portal_url' => $session->url,
        ];
    }

    /**
     * Handle successful checkout (Stripe webhook)
     */
    public function handleCheckoutCompleted(array $data): void
    {
        $metadata = $data['metadata'] ?? [];
        $subscriptionId = $data['subscription'];
        $customerId = $data['customer'];

        $userUuid = $metadata['user_uuid'] ?? null;
        if (! $userUuid) {
            \Illuminate\Support\Facades\Log::warning('Stripe checkout.session.completed: missing user_uuid in metadata');

            return;
        }

        $user = User::where('uuid', $userUuid)->first();
        if (! $user) {
            \Illuminate\Support\Facades\Log::warning('Stripe checkout.session.completed: user not found', ['user_uuid' => $userUuid]);

            return;
        }

        if (MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->where('payment_provider', 'stripe')->exists()) {
            \Illuminate\Support\Facades\Log::info('Stripe checkout.session.completed: already processed (idempotent)', ['subscription_id' => $subscriptionId]);

            return;
        }

        $stripeSubscription = $this->stripe->getSubscription($subscriptionId);
        $tier = $metadata['tier'] ?? 'starter';
        $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
        $byoAddons = isset($metadata['byo_addons']) ? json_decode($metadata['byo_addons'], true) : null;

        \Illuminate\Support\Facades\Log::info('Stripe checkout.session.completed: processing', [
            'subscription_id' => $subscriptionId,
            'user_uuid' => $userUuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
        ]);

        DB::transaction(function () use ($user, $subscriptionId, $customerId, $stripeSubscription, $tier, $billingCycle, $byoAddons, $data) {
            $existingSubscription = $this->getActiveSubscription($user);
            if ($existingSubscription && $existingSubscription->stripe_subscription_id !== $subscriptionId) {
                try {
                    $this->stripe->cancelSubscription($existingSubscription->stripe_subscription_id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to cancel previous subscription on upgrade', [
                        'old_sub' => $existingSubscription->stripe_subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $existingSubscription->update(['status' => 'canceled', 'canceled_at' => now()]);
            }

            MemoraSubscription::create([
                'uuid' => Str::uuid(),
                'user_uuid' => $user->uuid,
                'payment_provider' => 'stripe',
                'stripe_subscription_id' => $subscriptionId,
                'stripe_customer_id' => $customerId,
                'stripe_price_id' => $stripeSubscription->items->data[0]->price->id ?? '',
                'tier' => $tier,
                'billing_cycle' => $billingCycle,
                'status' => $stripeSubscription->status,
                'amount' => $stripeSubscription->items->data[0]->price->unit_amount ?? 0,
                'currency' => $stripeSubscription->currency,
                'current_period_start' => date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end),
                'metadata' => $byoAddons ? ['byo_addons' => $byoAddons] : null,
            ]);

            $previousTier = $user->memora_tier ?? 'starter';
            $historyMeta = ['checkout_session' => $data['id'] ?? null];
            if ($tier === 'byo' && $byoAddons) {
                $historyMeta['byo_addons'] = $byoAddons;
            }
            MemoraSubscriptionHistory::record(
                $user->uuid,
                $previousTier === 'starter' ? 'created' : 'upgraded',
                $previousTier,
                $tier,
                $billingCycle,
                $stripeSubscription->items->data[0]->price->unit_amount ?? 0,
                'stripe',
                $subscriptionId,
                $historyMeta,
                null,
                'usd'
            );

            $user->update([
                'memora_tier' => $tier,
                'stripe_customer_id' => $customerId,
            ]);
        });

        $this->notifySubscriptionActivated($user->fresh(), $tier, $billingCycle);
    }

    /**
     * Handle subscription updated (Stripe webhook)
     */
    public function handleSubscriptionUpdated(array $data): void
    {
        $subscriptionId = $data['id'];
        $status = $data['status'];

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->where('payment_provider', 'stripe')->first();
        if (! $subscription) {
            \Illuminate\Support\Facades\Log::warning('Stripe subscription.updated: subscription not found', ['subscription_id' => $subscriptionId]);

            return;
        }

        $subscription->update([
            'status' => $status,
            'current_period_start' => isset($data['current_period_start'])
                ? date('Y-m-d H:i:s', $data['current_period_start'])
                : $subscription->current_period_start,
            'current_period_end' => isset($data['current_period_end'])
                ? date('Y-m-d H:i:s', $data['current_period_end'])
                : $subscription->current_period_end,
            'canceled_at' => isset($data['canceled_at'])
                ? date('Y-m-d H:i:s', $data['canceled_at'])
                : null,
        ]);

        // If subscription is no longer active, downgrade user
        if (! in_array($status, ['active', 'trialing'])) {
            $user = $subscription->user;
            if ($user && $subscription->canceled_at && ! $subscription->onGracePeriod()) {
                $previousTier = $user->memora_tier;
                $user->update(['memora_tier' => 'starter']);

                MemoraSubscriptionHistory::record(
                    $user->uuid,
                    'downgraded',
                    $previousTier,
                    'starter',
                    null,
                    null,
                    'stripe',
                    $subscription->stripe_subscription_id,
                    null,
                    'Grace period ended'
                );
                $this->notifySubscriptionCancelled($user, $previousTier, $subscription->current_period_end);
            }
        }
    }

    /**
     * Handle subscription deleted/canceled (Stripe webhook)
     */
    public function handleSubscriptionDeleted(array $data): void
    {
        $subscriptionId = $data['id'];

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->where('payment_provider', 'stripe')->first();
        if (! $subscription) {
            \Illuminate\Support\Facades\Log::warning('Stripe subscription.deleted: subscription not found', ['subscription_id' => $subscriptionId]);

            return;
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        // Downgrade user to starter
        $user = $subscription->user;
        if ($user) {
            $previousTier = $user->memora_tier;
            $user->update(['memora_tier' => 'starter']);

            MemoraSubscriptionHistory::record(
                $user->uuid,
                'cancelled',
                $previousTier,
                'starter',
                null,
                null,
                'stripe',
                $subscriptionId,
                null,
                'Subscription cancelled'
            );
            $this->notifySubscriptionCancelled($user, $previousTier, $subscription->current_period_end);
        }
    }

    /**
     * Cancel subscription directly (test mode or non-Stripe providers)
     */
    public function cancelSubscriptionDirectly(User $user): void
    {
        $subscription = $this->getActiveSubscription($user);
        if (! $subscription) {
            throw new \RuntimeException('No active subscription found');
        }

        $provider = $subscription->payment_provider ?? 'stripe';
        $externalId = $subscription->stripe_subscription_id;

        try {
            $this->getSubscriptionProvider($provider)->cancelSubscription($externalId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Provider cancel failed, cancelling locally', [
                'provider' => $provider,
                'subscription_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
        }

        $previousTier = $subscription->tier;
        $periodEnd = $subscription->current_period_end;

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        $user->update(['memora_tier' => 'starter']);

        MemoraSubscriptionHistory::record(
            $user->uuid,
            'cancelled',
            $previousTier,
            'starter',
            null,
            null,
            $provider,
            $externalId,
            null,
            $provider === 'stripe' ? 'Cancelled in test mode' : 'Cancelled'
        );

        $this->notifySubscriptionCancelled($user->fresh(), $previousTier, $periodEnd);
    }

    /**
     * Paystack webhook: subscription created (after first payment with plan)
     */
    public function handlePaystackSubscriptionCreate(array $data): void
    {
        $subscription = $data['subscription'] ?? [];
        $customer = $data['customer'] ?? [];
        $email = is_string($customer) ? null : ($customer['email'] ?? null);
        if (! $email) {
            \Illuminate\Support\Facades\Log::warning('Paystack subscription.create: missing customer email', ['data_keys' => array_keys($data)]);

            return;
        }

        $subscriptionCode = $subscription['subscription_code'] ?? $subscription['id'] ?? null;
        if (! $subscriptionCode) {
            \Illuminate\Support\Facades\Log::warning('Paystack subscription.create: missing subscription_code', ['subscription_keys' => array_keys($subscription)]);

            return;
        }

        if (MemoraSubscription::where('stripe_subscription_id', $subscriptionCode)->where('payment_provider', 'paystack')->exists()) {
            \Illuminate\Support\Facades\Log::info('Paystack subscription.create: already processed (idempotent)', ['subscription_code' => $subscriptionCode]);
            Cache::forget('paystack_pending:'.$email);

            return;
        }

        $userByEmail = User::where('email', $email)->first();
        $existingByRef = $userByEmail
            ? MemoraSubscription::where('user_uuid', $userByEmail->uuid)->where('payment_provider', 'paystack')->where('status', 'active')
                ->whereRaw('LENGTH(stripe_subscription_id) < 20')
                ->first()
            : null;
        if ($existingByRef) {
            $existingByRef->update(['stripe_subscription_id' => $subscriptionCode]);
            \Illuminate\Support\Facades\Log::info('Paystack subscription.create: linked subscription_code to existing record', ['subscription_code' => $subscriptionCode]);
            Cache::forget('paystack_pending:'.$email);

            return;
        }

        $pending = Cache::get('paystack_pending:'.$email);
        if (! $pending) {
            \Illuminate\Support\Facades\Log::warning('Paystack subscription.create: no pending context for email', ['email' => $email]);

            return;
        }

        $userUuid = $pending['user_uuid'] ?? null;
        $user = $userUuid ? User::where('uuid', $userUuid)->first() : User::where('email', $email)->first();
        if (! $user) {
            \Illuminate\Support\Facades\Log::warning('Paystack subscription.create: user not found', ['email' => $email, 'user_uuid' => $userUuid]);

            return;
        }

        $customerCode = is_array($customer) ? ($customer['customer_code'] ?? null) : null;
        $amount = (int) ($subscription['amount'] ?? 0);
        $planData = $subscription['plan'] ?? [];
        $currency = strtolower($planData['currency'] ?? 'ngn');
        $nextPaymentDate = isset($subscription['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($subscription['next_payment_date'])) : null;
        $planInterval = strtolower((string) ($planData['interval'] ?? ''));
        $billingCycle = in_array($planInterval, ['annually', 'yearly'], true) ? 'annual' : 'monthly';
        $tier = $pending['tier'] ?? 'pro';
        $byoAddons = $this->normalizeByoAddons($pending['byo_addons'] ?? null);

        \Illuminate\Support\Facades\Log::info('Paystack subscription.create: processing', [
            'subscription_code' => $subscriptionCode,
            'email' => $email,
            'plan_interval' => $planInterval,
            'billing_cycle' => $billingCycle,
            'tier' => $tier,
            'amount' => $amount,
        ]);

        DB::transaction(function () use ($user, $subscriptionCode, $customerCode, $planData, $amount, $currency, $nextPaymentDate, $billingCycle, $byoAddons, $tier) {
            $existingSubscription = $this->getActiveSubscription($user);
            if ($existingSubscription) {
                $existingSubscription->update(['status' => 'canceled', 'canceled_at' => now()]);
            }

            MemoraSubscription::create([
                'uuid' => Str::uuid(),
                'user_uuid' => $user->uuid,
                'payment_provider' => 'paystack',
                'stripe_subscription_id' => $subscriptionCode,
                'stripe_customer_id' => $customerCode ?? $subscriptionCode,
                'stripe_price_id' => $planData['plan_code'] ?? 'paystack_plan',
                'tier' => $tier,
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'amount' => $amount,
                'currency' => $currency,
                'current_period_start' => now(),
                'current_period_end' => $nextPaymentDate ?? ($billingCycle === 'annual' ? now()->addYear() : now()->addMonth()),
                'metadata' => $byoAddons ? ['byo_addons' => $byoAddons] : null,
            ]);

            $previousTier = $user->memora_tier ?? 'starter';
            $historyMeta = $tier === 'byo' && $byoAddons ? ['byo_addons' => $byoAddons] : null;
            MemoraSubscriptionHistory::record(
                $user->uuid,
                $previousTier === 'starter' ? 'created' : 'upgraded',
                $previousTier,
                $tier,
                $billingCycle,
                $amount,
                'paystack',
                $subscriptionCode,
                $historyMeta,
                null,
                $currency
            );

            $user->update(['memora_tier' => $tier]);
        });

        $this->notifySubscriptionActivated($user->fresh(), $tier, $billingCycle);
        Cache::forget('paystack_pending:'.$email);
    }

    /**
     * Paystack webhook: subscription disabled/canceled
     */
    public function handlePaystackSubscriptionDisable(array $data): void
    {
        $subscription = $data['subscription'] ?? $data;
        $subscriptionCode = is_array($subscription) ? ($subscription['subscription_code'] ?? $subscription['id'] ?? null) : (string) $subscription;
        if (! $subscriptionCode) {
            return;
        }

        $model = MemoraSubscription::where('stripe_subscription_id', $subscriptionCode)
            ->where('payment_provider', 'paystack')
            ->first();
        if (! $model) {
            \Illuminate\Support\Facades\Log::warning('Paystack subscription.disable: subscription not found', ['subscription_code' => $subscriptionCode]);

            return;
        }

        $model->update(['status' => 'canceled', 'canceled_at' => now()]);
        $user = $model->user;
        if ($user) {
            $previousTier = $user->memora_tier;
            $user->update(['memora_tier' => 'starter']);
            MemoraSubscriptionHistory::record(
                $user->uuid,
                'cancelled',
                $previousTier,
                'starter',
                null,
                null,
                'paystack',
                $subscriptionCode,
                null,
                'Subscription cancelled'
            );
            $this->notifySubscriptionCancelled($user->fresh(), $previousTier, $model->current_period_end);
        }
    }

    /**
     * Paystack webhook: invoice payment failed (recurring charge failed)
     */
    public function handlePaystackInvoicePaymentFailed(array $data): void
    {
        $subscription = $data['subscription'] ?? [];
        $subscriptionCode = is_array($subscription) ? ($subscription['subscription_code'] ?? $subscription['id'] ?? null) : null;
        if (! $subscriptionCode) {
            \Illuminate\Support\Facades\Log::warning('Paystack invoice.payment_failed: missing subscription_code', ['data_keys' => array_keys($data)]);

            return;
        }

        $model = MemoraSubscription::where('stripe_subscription_id', $subscriptionCode)
            ->where('payment_provider', 'paystack')
            ->first();
        if (! $model) {
            \Illuminate\Support\Facades\Log::warning('Paystack invoice.payment_failed: subscription not found', ['subscription_code' => $subscriptionCode]);

            return;
        }

        $user = $model->user;
        if ($user) {
            $reason = $data['description'] ?? $data['message'] ?? 'Payment could not be processed.';
            $this->notifyPaymentFailed($user, $reason);
        }
    }

    /**
     * Paystack webhook: charge.success for initial payment (no subscription object yet). Create subscription from metadata + plan.
     */
    public function handlePaystackInitialChargeSuccess(array $data): void
    {
        $metadata = $data['metadata'] ?? [];
        $userUuid = $metadata['user_uuid'] ?? null;
        if (! $userUuid) {
            return;
        }
        $user = User::where('uuid', $userUuid)->first();
        if (! $user) {
            return;
        }
        $reference = $data['reference'] ?? null;
        if (! $reference) {
            return;
        }
        if (MemoraSubscription::where('payment_provider', 'paystack')->where('stripe_subscription_id', $reference)->exists()) {
            \Illuminate\Support\Facades\Log::info('Paystack initial charge: already processed (idempotent)', ['reference' => $reference]);

            return;
        }
        $tier = $metadata['tier'] ?? 'pro';
        $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
        $plan = $data['plan'] ?? [];
        $amount = (int) ($data['amount'] ?? $plan['amount'] ?? 0);
        $currency = strtolower((string) ($plan['currency'] ?? 'ngn'));
        $planCode = $plan['plan_code'] ?? $plan['id'] ?? 'paystack_plan';
        $customer = $data['customer'] ?? [];
        $customerCode = is_array($customer) ? ($customer['customer_code'] ?? null) : null;
        $interval = strtolower((string) ($plan['interval'] ?? 'monthly'));
        $periodEnd = in_array($interval, ['annually', 'yearly'], true) ? now()->addYear() : now()->addMonth();
        $byoAddons = $this->normalizeByoAddons($metadata['byo_addons'] ?? null);

        DB::transaction(function () use ($user, $reference, $customerCode, $planCode, $amount, $currency, $periodEnd, $billingCycle, $byoAddons, $tier) {
            $existing = $this->getActiveSubscription($user);
            if ($existing) {
                $existing->update(['status' => 'canceled', 'canceled_at' => now()]);
            }
            MemoraSubscription::create([
                'uuid' => Str::uuid(),
                'user_uuid' => $user->uuid,
                'payment_provider' => 'paystack',
                'stripe_subscription_id' => $reference,
                'stripe_customer_id' => $customerCode ?? $reference,
                'stripe_price_id' => $planCode,
                'tier' => $tier,
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'amount' => $amount,
                'currency' => $currency,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'metadata' => $byoAddons ? ['byo_addons' => $byoAddons] : null,
            ]);
            $previousTier = $user->memora_tier ?? 'starter';
            $historyMeta = $tier === 'byo' && $byoAddons ? ['byo_addons' => $byoAddons] : null;
            MemoraSubscriptionHistory::record(
                $user->uuid,
                $previousTier === 'starter' ? 'created' : 'upgraded',
                $previousTier,
                $tier,
                $billingCycle,
                $amount,
                'paystack',
                $reference,
                $historyMeta,
                null,
                $currency
            );
            $user->update(['memora_tier' => $tier]);
        });
        $this->notifySubscriptionActivated($user->fresh(), $tier, $billingCycle);
    }

    /**
     * Flutterwave webhook: charge.completed for initial payment. Create subscription from metadata + cache.
     */
    public function handleFlutterwaveInitialChargeSuccess(array $data): void
    {
        $txRef = $data['tx_ref'] ?? $data['reference'] ?? null;
        if (! $txRef) {
            \Illuminate\Support\Facades\Log::warning('Flutterwave initial charge: missing tx_ref/reference', ['data_keys' => array_keys($data)]);

            return;
        }

        $transactionId = $data['id'] ?? $data['tx_id'] ?? $txRef;
        if (MemoraSubscription::where('payment_provider', 'flutterwave')
            ->whereIn('stripe_subscription_id', [$txRef, $transactionId])
            ->exists()) {
            \Illuminate\Support\Facades\Log::info('Flutterwave initial charge: already processed (idempotent)', ['tx_ref' => $txRef]);
            Cache::forget('flutterwave_pending:'.$txRef);

            return;
        }
        $metadata = $data['meta'] ?? $data['metadata'] ?? [];
        $userUuid = $metadata['user_uuid'] ?? null;

        if (! $userUuid) {
            $pending = Cache::get('flutterwave_pending:'.$txRef);
            if ($pending) {
                $userUuid = $pending['user_uuid'] ?? null;
            }
        }

        if (! $userUuid) {
            \Illuminate\Support\Facades\Log::warning('Flutterwave initial charge: no user_uuid in meta or cache', ['tx_ref' => $txRef]);

            return;
        }

        $user = User::where('uuid', $userUuid)->first();
        if (! $user) {
            \Illuminate\Support\Facades\Log::warning('Flutterwave initial charge: user not found', ['user_uuid' => $userUuid]);

            return;
        }

        $pending = Cache::get('flutterwave_pending:'.$txRef);
        $tier = $metadata['tier'] ?? $pending['tier'] ?? 'pro';
        $billingCycle = $metadata['billing_cycle'] ?? $pending['billing_cycle'] ?? 'monthly';
        $byoAddons = $this->normalizeByoAddons($metadata['byo_addons'] ?? $pending['byo_addons'] ?? null);
        $currency = strtolower((string) ($data['currency'] ?? $pending['currency'] ?? 'ngn'));
        $amountMain = (float) ($data['amount'] ?? 0);
        $amountSubunits = $amountMain >= 1 && $amountMain < 1000000
            ? (int) round($amountMain * 100)
            : (int) $amountMain;

        $periodEnd = $billingCycle === 'annual' ? now()->addYear() : now()->addMonth();

        $customerRaw = $data['customer'] ?? null;
        $stripeCustomerId = '';
        if ($customerRaw !== null) {
            $stripeCustomerId = is_array($customerRaw) ? (string) ($customerRaw['id'] ?? '') : (string) $customerRaw;
        }
        if ($stripeCustomerId === '') {
            $stripeCustomerId = 'fw_'.$transactionId;
        }

        DB::transaction(function () use ($user, $transactionId, $tier, $billingCycle, $amountSubunits, $currency, $periodEnd, $byoAddons, $stripeCustomerId) {
            $existing = $this->getActiveSubscription($user);
            if ($existing) {
                $existing->update(['status' => 'canceled', 'canceled_at' => now()]);
            }
            MemoraSubscription::create([
                'uuid' => Str::uuid(),
                'user_uuid' => $user->uuid,
                'payment_provider' => 'flutterwave',
                'stripe_subscription_id' => $transactionId,
                'stripe_customer_id' => $stripeCustomerId,
                'stripe_price_id' => 'flutterwave',
                'tier' => $tier,
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'amount' => $amountSubunits,
                'currency' => $currency,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'metadata' => $byoAddons ? ['byo_addons' => $byoAddons] : null,
            ]);
            $previousTier = $user->memora_tier ?? 'starter';
            $historyMeta = $tier === 'byo' && $byoAddons ? ['byo_addons' => $byoAddons] : null;
            MemoraSubscriptionHistory::record(
                $user->uuid,
                $previousTier === 'starter' ? 'created' : 'upgraded',
                $previousTier,
                $tier,
                $billingCycle,
                $amountSubunits,
                'flutterwave',
                $transactionId,
                $historyMeta,
                null,
                $currency
            );
            $user->update(['memora_tier' => $tier]);
        });

        $this->notifySubscriptionActivated($user->fresh(), $tier, $billingCycle);
        Cache::forget('flutterwave_pending:'.$txRef);
    }

    /**
     * Paystack webhook: charge.success for recurring (renewal). Update period and notify.
     */
    public function handlePaystackChargeSuccess(array $data): void
    {
        $subscription = $data['subscription'] ?? [];
        if (! is_array($subscription)) {
            return;
        }
        $subscriptionCode = $subscription['subscription_code'] ?? $subscription['id'] ?? null;
        if (! $subscriptionCode) {
            return;
        }

        $model = MemoraSubscription::where('stripe_subscription_id', $subscriptionCode)
            ->where('payment_provider', 'paystack')
            ->first();
        if (! $model) {
            return;
        }

        $nextPaymentDate = isset($subscription['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($subscription['next_payment_date'])) : null;
        if ($nextPaymentDate) {
            $model->update(['current_period_end' => $nextPaymentDate]);
        }

        $user = $model->user;
        if ($user) {
            $this->notifySubscriptionRenewed($user, $model->tier, $model->billing_cycle, $model->current_period_end?->toDateTimeString());
        }
    }

    /**
     * PayPal webhook: BILLING.SUBSCRIPTION.ACTIVATED – subscription approved
     */
    public function handlePayPalSubscriptionActivated(array $data): void
    {
        $resource = $data['resource'] ?? $data;
        $subscriptionId = $resource['id'] ?? null;
        $customId = $resource['custom_id'] ?? null;

        if (! $subscriptionId) {
            \Illuminate\Support\Facades\Log::warning('PayPal subscription.activated: missing id', ['data_keys' => array_keys($data)]);

            return;
        }

        if (MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->where('payment_provider', 'paypal')->exists()) {
            \Illuminate\Support\Facades\Log::info('PayPal subscription.activated: already processed (idempotent)', ['subscription_id' => $subscriptionId]);
            if ($customId) {
                Cache::forget("paypal_pending:{$customId}");
            }

            return;
        }

        $pending = $customId ? Cache::get("paypal_pending:{$customId}") : null;
        if (! $pending) {
            \Illuminate\Support\Facades\Log::warning('PayPal subscription.activated: no pending context for custom_id', ['custom_id' => $customId]);

            return;
        }

        $userUuid = $pending['user_uuid'] ?? null;
        $user = $userUuid ? User::where('uuid', $userUuid)->first() : null;
        if (! $user) {
            \Illuminate\Support\Facades\Log::warning('PayPal subscription.activated: user not found', ['user_uuid' => $userUuid]);

            return;
        }

        $tier = $pending['tier'] ?? 'pro';
        $billingCycle = $pending['billing_cycle'] ?? 'monthly';
        $byoAddons = $this->normalizeByoAddons($pending['byo_addons'] ?? null);

        $billingInfo = $resource['billing_info'] ?? [];
        $nextBillingTime = $billingInfo['next_billing_time'] ?? null;
        $periodEnd = $nextBillingTime ? date('Y-m-d H:i:s', strtotime($nextBillingTime)) : ($billingCycle === 'annual' ? now()->addYear() : now()->addMonth());

        $planId = $resource['plan_id'] ?? 'paypal_plan';
        $subscriber = $resource['subscriber'] ?? [];
        $payerId = is_array($subscriber) ? ($subscriber['payer_id'] ?? null) : null;

        $amount = $this->calculateAmount($tier, $billingCycle, $byoAddons);
        $currency = 'usd';

        \Illuminate\Support\Facades\Log::info('PayPal subscription.activated: processing', [
            'subscription_id' => $subscriptionId,
            'user_uuid' => $userUuid,
            'tier' => $tier,
            'billing_cycle' => $billingCycle,
        ]);

        DB::transaction(function () use ($user, $subscriptionId, $payerId, $planId, $amount, $currency, $periodEnd, $billingCycle, $byoAddons, $tier) {
            $existingSubscription = $this->getActiveSubscription($user);
            if ($existingSubscription) {
                $existingSubscription->update(['status' => 'canceled', 'canceled_at' => now()]);
            }

            MemoraSubscription::create([
                'uuid' => Str::uuid(),
                'user_uuid' => $user->uuid,
                'payment_provider' => 'paypal',
                'stripe_subscription_id' => $subscriptionId,
                'stripe_customer_id' => $payerId ?? $subscriptionId,
                'stripe_price_id' => $planId,
                'tier' => $tier,
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'amount' => $amount,
                'currency' => $currency,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'metadata' => $byoAddons ? ['byo_addons' => $byoAddons] : null,
            ]);

            $previousTier = $user->memora_tier ?? 'starter';
            $historyMeta = $tier === 'byo' && $byoAddons ? ['byo_addons' => $byoAddons] : null;
            MemoraSubscriptionHistory::record(
                $user->uuid,
                $previousTier === 'starter' ? 'created' : 'upgraded',
                $previousTier,
                $tier,
                $billingCycle,
                $amount,
                'paypal',
                $subscriptionId,
                $historyMeta,
                null,
                $currency
            );

            $user->update(['memora_tier' => $tier]);
        });

        $this->notifySubscriptionActivated($user->fresh(), $tier, $billingCycle);
        if ($customId) {
            Cache::forget("paypal_pending:{$customId}");
        }
    }

    /**
     * PayPal webhook: BILLING.SUBSCRIPTION.CANCELLED / SUSPENDED / EXPIRED
     */
    public function handlePayPalSubscriptionCancelled(array $data): void
    {
        $resource = $data['resource'] ?? $data;
        $subscriptionId = is_array($resource) ? ($resource['id'] ?? null) : (string) $resource;

        if (! $subscriptionId) {
            return;
        }

        $model = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)
            ->where('payment_provider', 'paypal')
            ->first();
        if (! $model) {
            \Illuminate\Support\Facades\Log::warning('PayPal subscription.cancelled: subscription not found', ['subscription_id' => $subscriptionId]);

            return;
        }

        $model->update(['status' => 'canceled', 'canceled_at' => now()]);
        $user = $model->user;
        if ($user) {
            $previousTier = $user->memora_tier;
            $user->update(['memora_tier' => 'starter']);
            MemoraSubscriptionHistory::record(
                $user->uuid,
                'cancelled',
                $previousTier,
                'starter',
                null,
                null,
                'paypal',
                $subscriptionId,
                null,
                'Subscription cancelled'
            );
            $this->notifySubscriptionCancelled($user->fresh(), $previousTier, $model->current_period_end);
        }
    }

    /**
     * PayPal webhook: BILLING.SUBSCRIPTION.PAYMENT.FAILED – resource.id is subscription ID
     */
    public function handlePayPalPaymentFailed(array $data): void
    {
        $resource = $data['resource'] ?? $data;
        $subscriptionId = is_array($resource) ? ($resource['id'] ?? null) : null;

        if (! $subscriptionId) {
            \Illuminate\Support\Facades\Log::warning('PayPal payment.failed: missing subscription id', ['data_keys' => array_keys($data)]);

            return;
        }

        $model = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)
            ->where('payment_provider', 'paypal')
            ->first();
        if (! $model) {
            \Illuminate\Support\Facades\Log::warning('PayPal payment.failed: subscription not found', ['subscription_id' => $subscriptionId]);

            return;
        }

        $user = $model->user;
        if ($user) {
            $reason = $resource['reason'] ?? $resource['description'] ?? 'Payment could not be processed.';
            $this->notifyPaymentFailed($user, $reason);
        }
    }

    /**
     * PayPal webhook: PAYMENT.SALE.COMPLETED (renewal) – billing_agreement_id is the subscription ID
     */
    public function handlePayPalChargeSuccess(array $data): void
    {
        $resource = $data['resource'] ?? $data;
        $subscriptionId = $resource['billing_agreement_id'] ?? null;
        if (! $subscriptionId) {
            return;
        }

        $model = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)
            ->where('payment_provider', 'paypal')
            ->first();
        if (! $model) {
            return;
        }

        $paypal = app()->make(PayPalProvider::class);
        try {
            $sub = $paypal->getSubscription($subscriptionId);
            $nextBilling = $sub['billing_info']['next_billing_time'] ?? null;
            if ($nextBilling) {
                $model->update(['current_period_end' => date('Y-m-d H:i:s', strtotime($nextBilling))]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PayPal charge success: could not fetch subscription', ['id' => $subscriptionId, 'error' => $e->getMessage()]);
        }

        $user = $model->user;
        if ($user) {
            $this->notifySubscriptionRenewed($user, $model->tier, $model->billing_cycle, $model->current_period_end?->toDateTimeString());
        }
    }

    /**
     * Get user's active subscription
     */
    public function getActiveSubscription(User $user): ?MemoraSubscription
    {
        return MemoraSubscription::where('user_uuid', $user->uuid)
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();
    }

    /**
     * Validate if user can downgrade to Starter (usage fits within Starter limits)
     *
     * @return array{valid: bool, errors: array<string>, limits: array}
     */
    public function validateDowngradeToStarter(User $user): array
    {
        $starterTier = MemoraPricingTier::getBySlug('starter');
        $storageLimit = $starterTier ? (int) $starterTier->storage_bytes : (5 * 1024 * 1024 * 1024);
        $projectLimit = $starterTier ? (int) ($starterTier->project_limit ?? 3) : 3;
        $collectionLimit = $starterTier ? (int) ($starterTier->collection_limit ?? 2) : 2;

        $errors = [];

        $totalStorage = $this->storageService->getTotalStorageUsed($user->uuid);
        if ($totalStorage > $storageLimit) {
            $usedGb = round($totalStorage / (1024 ** 3), 1);
            $limitGb = round($storageLimit / (1024 ** 3), 1);
            $errors[] = "Storage: {$usedGb} GB used exceeds Starter limit ({$limitGb} GB). Delete files to downgrade.";
        }

        $projectCount = MemoraProject::where('user_uuid', $user->uuid)->count();
        if ($projectLimit !== null && $projectCount > $projectLimit) {
            $errors[] = "Projects: {$projectCount} exceeds Starter limit ({$projectLimit}). Archive or delete projects.";
        }

        $collectionCount = MemoraCollection::where('user_uuid', $user->uuid)->count();
        if ($collectionLimit !== null && $collectionCount > $collectionLimit) {
            $errors[] = "Collections: {$collectionCount} exceeds Starter limit ({$collectionLimit}). Remove collections.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'limits' => [
                'storage_bytes' => $storageLimit,
                'project_limit' => $projectLimit,
                'collection_limit' => $collectionLimit,
            ],
            'usage' => [
                'storage_bytes' => $totalStorage,
                'project_count' => $projectCount,
                'collection_count' => $collectionCount,
            ],
        ];
    }

    /**
     * Build line items for Stripe checkout
     */
    protected function buildLineItems(string $tier, string $billingCycle, string $currency, ?array $byoAddons): array
    {
        $lineItems = [];
        $currencyUpper = strtoupper($currency);

        if ($tier === 'byo') {
            $config = MemoraByoConfig::getConfig();
            if (! $config) {
                throw new \RuntimeException('BYO config not found');
            }
            $basePriceUsd = $billingCycle === 'annual'
                ? (int) $config->base_price_annual_cents
                : (int) $config->base_price_monthly_cents;
            $basePrice = $currency === 'usd' ? $basePriceUsd : $this->currencyService->convert($basePriceUsd, 'USD', $currencyUpper);

            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'Memora - Build Your Own (Base)',
                    ],
                    'unit_amount' => $basePrice,
                    'recurring' => [
                        'interval' => $billingCycle === 'annual' ? 'year' : 'month',
                    ],
                ],
                'quantity' => 1,
            ];

            if ($byoAddons) {
                $addons = MemoraByoAddon::whereIn('slug', array_keys($byoAddons))->get();
                foreach ($addons as $addon) {
                    $quantity = (int) ($byoAddons[$addon->slug] ?? 1);
                    if ($quantity <= 0) {
                        continue;
                    }

                    $addonPriceUsd = $billingCycle === 'annual'
                        ? (int) $addon->price_annual_cents
                        : (int) $addon->price_monthly_cents;
                    $addonPrice = $currency === 'usd' ? $addonPriceUsd : $this->currencyService->convert($addonPriceUsd, 'USD', $currencyUpper);

                    $lineItems[] = [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => "Memora - {$addon->label}",
                            ],
                            'unit_amount' => $addonPrice,
                            'recurring' => [
                                'interval' => $billingCycle === 'annual' ? 'year' : 'month',
                            ],
                        ],
                        'quantity' => $quantity,
                    ];
                }
            }
        } else {
            $tierData = MemoraPricingTier::getBySlug($tier);
            if (! $tierData) {
                throw new \RuntimeException("Invalid tier: {$tier}");
            }

            $priceUsd = $billingCycle === 'annual'
                ? (int) $tierData->price_annual_cents
                : (int) $tierData->price_monthly_cents;
            $price = $currency === 'usd' ? $priceUsd : $this->currencyService->convert($priceUsd, 'USD', $currencyUpper);

            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => "Memora - {$tierData->name}",
                        'description' => $tierData->description,
                    ],
                    'unit_amount' => $price,
                    'recurring' => [
                        'interval' => $billingCycle === 'annual' ? 'year' : 'month',
                    ],
                ],
                'quantity' => 1,
            ];
        }

        return $lineItems;
    }

    /**
     * VAT amount in same currency as subtotal (smallest unit). Rate from config; 0 = no VAT.
     */
    protected function vatCents(int $subtotalCents): int
    {
        $rate = (float) config('pricing.vat_rate', 0);
        return $rate > 0 ? (int) floor($subtotalCents * $rate) : 0;
    }

    /**
     * Total = subtotal + VAT. Use when charging; do not mutate subtotal.
     */
    protected function totalWithVat(int $subtotalCents): int
    {
        return $subtotalCents + $this->vatCents($subtotalCents);
    }

    /**
     * Calculate total amount for a subscription
     */
    protected function calculateAmount(string $tier, string $billingCycle, ?array $byoAddons): int
    {
        if ($tier === 'byo') {
            $config = MemoraByoConfig::getConfig();
            if (! $config) {
                return 0;
            }
            $basePrice = $billingCycle === 'annual'
                ? (int) $config->base_price_annual_cents
                : (int) $config->base_price_monthly_cents;
            $total = $basePrice;

            if ($byoAddons) {
                $addons = MemoraByoAddon::whereIn('slug', array_keys($byoAddons))->get();
                foreach ($addons as $addon) {
                    $quantity = (int) ($byoAddons[$addon->slug] ?? 1);
                    if ($quantity <= 0) {
                        continue;
                    }
                    $addonPrice = $billingCycle === 'annual'
                        ? (int) $addon->price_annual_cents
                        : (int) $addon->price_monthly_cents;
                    $total += $addonPrice * $quantity;
                }
            }

            return $total;
        }

        $tierData = MemoraPricingTier::getBySlug($tier);
        if (! $tierData) {
            return 0;
        }

        return $billingCycle === 'annual'
            ? (int) $tierData->price_annual_cents
            : (int) $tierData->price_monthly_cents;
    }

    /**
     * Get Stripe publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $this->stripe->getPublicKey();
    }

    /**
     * Get subscription history for a user
     */
    public function getHistory(User $user, int $limit = 20): array
    {
        $tierService = app(\App\Services\Subscription\TierService::class);

        return MemoraSubscriptionHistory::where('user_uuid', $user->uuid)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($h) use ($tierService, $user) {
                $item = [
                    'id' => $h->id,
                    'event_type' => $h->event_type,
                    'from_tier' => $h->from_tier,
                    'to_tier' => $h->to_tier,
                    'billing_cycle' => $h->billing_cycle,
                    'amount_cents' => $h->amount_cents,
                    'currency' => $h->currency,
                    'payment_provider' => $h->payment_provider,
                    'payment_reference' => $h->payment_reference,
                    'metadata' => $h->metadata,
                    'notes' => $h->notes,
                    'created_at' => $h->created_at->toIso8601String(),
                    'updated_at' => $h->updated_at->toIso8601String(),
                ];
                if ($h->to_tier === 'byo') {
                    $addons = [];
                    if (! empty($h->metadata['byo_addons'])) {
                        $raw = $h->metadata['byo_addons'];
                        $addons = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?? []) : []);
                    }
                    if ($addons !== []) {
                        $resolved = $tierService->resolveByoPlanFromAddons($addons);
                        $item['memora_features'] = $resolved['features'];
                        $item['memora_capabilities'] = $resolved['capabilities'];
                        $item['byo_addons_list'] = $tierService->getByoAddonsDisplay($addons);
                    } elseif ($user->memora_tier === 'byo') {
                        $display = $tierService->getByoPlanDisplay($user);
                        $item['memora_features'] = $display['features'];
                        $item['memora_capabilities'] = $display['capabilities'];
                        $item['byo_addons_list'] = $display['byo_addons_list'];
                    }
                }

                return $item;
            })
            ->toArray();
    }
}
