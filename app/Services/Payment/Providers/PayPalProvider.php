<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\SubscriptionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayPalProvider implements PaymentProviderInterface, SubscriptionProviderInterface
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('payment.providers.paypal', []);
    }

    protected function ensureConfigured(): void
    {
        $clientId = $this->config['client_id'] ?? null;
        $clientSecret = $this->config['client_secret'] ?? null;
        if (empty($clientId) || empty($clientSecret)) {
            throw new \RuntimeException('PayPal client_id and client_secret are not configured. Set PAYPAL_TEST_CLIENT_ID/SECRET or PAYPAL_LIVE_CLIENT_ID/SECRET.');
        }
    }

    protected function getBaseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://api-m.sandbox.paypal.com', '/');
    }

    protected function isTestMode(): bool
    {
        return ! empty($this->config['test_mode']);
    }

    protected function getAccessToken(): string
    {
        $this->ensureConfigured();

        $cacheKey = 'paypal_access_token:'.($this->isTestMode() ? 'test' : 'live');
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $response = Http::withBasicAuth(
            $this->config['client_id'],
            $this->config['client_secret']
        )->asForm()
            ->post($this->getBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $data = $response->json();
        if (! $response->successful() || empty($data['access_token'])) {
            throw new \RuntimeException($data['error_description'] ?? $data['message'] ?? 'PayPal OAuth failed');
        }

        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        Cache::put($cacheKey, $data['access_token'], now()->addSeconds($expiresIn - 60));

        return $data['access_token'];
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'Content-Type' => 'application/json',
        ];
    }

    public function createProduct(): string
    {
        $cacheKey = 'paypal_product:'.($this->isTestMode() ? 'test' : 'live');
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $response = Http::withHeaders($this->headers())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
            ->post($this->getBaseUrl().'/v1/catalogs/products', [
                'name' => 'Memora Subscription',
                'description' => 'Memora photo gallery and proofing subscription',
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ]);

        $data = $response->json();
        if (! $response->successful() || empty($data['id'])) {
            throw new \RuntimeException($data['message'] ?? $data['error_description'] ?? 'PayPal product creation failed');
        }

        Cache::put($cacheKey, $data['id'], 86400 * 30);

        return $data['id'];
    }

    public function createPlan(string $productId, string $name, int $amountCents, string $currency, string $billingCycle): string
    {
        $mode = $this->isTestMode() ? 'test' : 'live';
        $amount = number_format($amountCents / 100, 2, '.', '');
        $cacheKey = "paypal_plan:{$mode}:{$name}:{$billingCycle}:{$currency}:{$amount}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $intervalUnit = 'MONTH';
        $intervalCount = $billingCycle === 'annual' ? 12 : 1;
        $totalCycles = $billingCycle === 'annual' ? 0 : 0; // 0 = infinite

        $body = [
            'product_id' => $productId,
            'name' => $name,
            'description' => $name,
            'status' => 'ACTIVE',
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => $intervalUnit,
                        'interval_count' => $intervalCount,
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => $totalCycles,
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => $amount,
                            'currency_code' => strtoupper($currency),
                        ],
                    ],
                ],
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'payment_failure_threshold' => 3,
            ],
        ];

        $response = Http::withHeaders($this->headers())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
            ->post($this->getBaseUrl().'/v1/billing/plans', $body);

        $data = $response->json();
        if (! $response->successful() || empty($data['id'])) {
            throw new \RuntimeException($data['message'] ?? $data['details'][0]['description'] ?? 'PayPal plan creation failed');
        }

        Cache::put($cacheKey, $data['id'], 86400 * 7);

        return $data['id'];
    }

    /**
     * Initialize subscription (mirror Paystack initializeSubscriptionTransaction).
     * Returns checkout URL and subscription ID.
     */
    public function initializeSubscription(array $data): array
    {
        $planId = $data['plan_id'] ?? null;
        $customId = $data['custom_id'] ?? null;
        $returnUrl = $data['return_url'] ?? null;
        $cancelUrl = $data['cancel_url'] ?? null;
        $subscriberEmail = $data['subscriber_email'] ?? null;

        if (! $planId || ! $customId || ! $returnUrl || ! $cancelUrl) {
            throw new \InvalidArgumentException('PayPal initializeSubscription requires plan_id, custom_id, return_url, cancel_url');
        }

        $body = [
            'plan_id' => $planId,
            'custom_id' => substr($customId, 0, 127),
            'application_context' => [
                'brand_name' => 'Memora',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        if ($subscriberEmail) {
            $body['subscriber'] = [
                'email_address' => $subscriberEmail,
            ];
        }

        $response = Http::withHeaders($this->headers())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
            ->post($this->getBaseUrl().'/v1/billing/subscriptions', $body);

        $responseData = $response->json();
        if (! $response->successful() || empty($responseData['id'])) {
            $msg = $responseData['message'] ?? $responseData['error_description'] ?? null;
            if (isset($responseData['details'][0]['description'])) {
                $msg = $responseData['details'][0]['description'];
            }
            throw new \RuntimeException($msg ?? 'PayPal subscription creation failed');
        }

        $approveUrl = null;
        $links = $responseData['links'] ?? [];
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = $link['href'] ?? null;
                break;
            }
        }

        if (! $approveUrl) {
            throw new \RuntimeException('PayPal subscription created but no approval URL returned');
        }

        return [
            'authorization_url' => $approveUrl,
            'checkout_url' => $approveUrl,
            'subscription_id' => $responseData['id'],
            'reference' => $responseData['id'],
        ];
    }

    public function getSubscription(string $subscriptionId): array
    {
        $response = Http::withHeaders($this->headers())
            ->get($this->getBaseUrl().'/v1/billing/subscriptions/'.$subscriptionId);

        $data = $response->json();
        if (! $response->successful()) {
            throw new \RuntimeException($data['message'] ?? $data['details'][0]['description'] ?? 'PayPal get subscription failed');
        }

        return $data;
    }

    public function charge(array $paymentData): PaymentResult
    {
        throw new \RuntimeException('PayPal one-time charge not implemented. Use subscriptions.');
    }

    public function refund(string $transactionId, ?int $amount = null): PaymentResult
    {
        throw new \RuntimeException('PayPal refund not yet fully implemented');
    }

    public function getPaymentStatus(string $transactionId): PaymentResult
    {
        throw new \RuntimeException('PayPal getPaymentStatus not yet fully implemented');
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        return true;
    }

    /**
     * Verify PayPal webhook using verify-webhook-signature API.
     */
    public function verifyWebhook(Request $request): bool
    {
        $webhookId = $this->config['webhook_id'] ?? null;
        if (! $webhookId) {
            return true;
        }

        $this->ensureConfigured();

        $payload = $request->getContent();

        $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME');
        $certUrl = $request->header('PAYPAL-CERT-URL');
        $authAlgo = $request->header('PAYPAL-AUTH-ALGO');
        $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG');

        if (! $transmissionId || ! $transmissionTime || ! $certUrl || ! $authAlgo || ! $transmissionSig) {
            return false;
        }

        $body = [
            'auth_algo' => $authAlgo,
            'cert_url' => $certUrl,
            'transmission_id' => $transmissionId,
            'transmission_sig' => $transmissionSig,
            'transmission_time' => $transmissionTime,
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($payload, true),
        ];

        $response = Http::withHeaders($this->headers())
            ->post($this->getBaseUrl().'/v1/notifications/verify-webhook-signature', $body);

        $data = $response->json();
        if (! $response->successful()) {
            return false;
        }

        return ($data['verification_status'] ?? '') === 'SUCCESS';
    }

    public function getSupportedCurrencies(): array
    {
        return ['usd', 'eur', 'gbp', 'cad', 'aud'];
    }

    public function createSubscription(array $subscriptionData): SubscriptionResult
    {
        $result = $this->initializeSubscription([
            'plan_id' => $subscriptionData['plan_id'] ?? null,
            'custom_id' => $subscriptionData['custom_id'] ?? Str::random(32),
            'return_url' => $subscriptionData['return_url'] ?? '',
            'cancel_url' => $subscriptionData['cancel_url'] ?? '',
            'subscriber_email' => $subscriptionData['customer_email'] ?? null,
        ]);

        return new SubscriptionResult(
            subscriptionId: $result['subscription_id'],
            status: 'APPROVAL_PENDING',
            provider: 'paypal',
            metadata: $result,
        );
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->getBaseUrl().'/v1/billing/subscriptions/'.$subscriptionId.'/cancel', [
                'reason' => 'Customer requested cancellation',
            ]);

        if (! $response->successful()) {
            $data = $response->json();
            throw new \RuntimeException($data['message'] ?? $data['details'][0]['description'] ?? 'PayPal cancel failed');
        }

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: 'canceled',
            provider: 'paypal',
        );
    }

    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult
    {
        throw new \RuntimeException('PayPal updateSubscription not implemented');
    }

    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult
    {
        $sub = $this->getSubscription($subscriptionId);
        $billingInfo = $sub['billing_info'] ?? [];
        $nextBilling = $billingInfo['next_billing_time'] ?? null;

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: strtolower($sub['status'] ?? 'unknown'),
            provider: 'paypal',
            customerId: $sub['subscriber']['payer_id'] ?? null,
            currentPeriodEnd: $nextBilling ? date('Y-m-d H:i:s', strtotime($nextBilling)) : null,
            metadata: $sub,
        );
    }

    public function getPublicKey(): string
    {
        return $this->config['client_id'] ?? '';
    }
}
