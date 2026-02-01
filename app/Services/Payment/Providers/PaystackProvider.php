<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\SubscriptionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackProvider implements PaymentProviderInterface, SubscriptionProviderInterface
{
    protected array $config;

    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->config = config('payment.providers.paystack', []);

        if (empty($this->config['secret_key'])) {
            throw new \RuntimeException('Paystack secret key is not configured. Set PAYSTACK_TEST_SECRET_KEY (test) or PAYSTACK_LIVE_SECRET_KEY (live).');
        }
    }

    protected function isTestMode(): bool
    {
        return ! empty($this->config['test_mode']);
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['secret_key'],
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Create a plan (used for subscription checkout). Same API in test and live.
     */
    public function createPlan(string $name, int $amountSubunits, string $interval, string $currency = 'NGN'): string
    {
        $body = [
            'name' => $name,
            'amount' => $amountSubunits,
            'interval' => $interval === 'annual' ? 'annually' : 'monthly',
        ];
        if ($currency) {
            $body['currency'] = strtoupper($currency);
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/plan", $body);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException($data['message'] ?? 'Paystack plan creation failed');
        }

        $plan = $data['data'] ?? [];

        return (string) ($plan['plan_code'] ?? $plan['id'] ?? '');
    }

    /**
     * Initialize a subscription transaction (plan-based). Returns checkout URL and reference.
     * Paystack requires amount and currency in the body even when plan is set (they override with plan amount).
     */
    public function initializeSubscriptionTransaction(string $email, string $planCode, int $amountSubunits, string $currency, string $callbackUrl, array $metadata = []): array
    {
        $body = [
            'email' => $email,
            'amount' => (string) $amountSubunits,
            'currency' => strtoupper($currency),
            'plan' => $planCode,
            'callback_url' => $callbackUrl,
        ];
        if (! empty($metadata)) {
            $body['metadata'] = $metadata;
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/transaction/initialize", $body);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException($data['message'] ?? 'Paystack transaction initialize failed');
        }

        $accessData = $data['data'] ?? [];

        return [
            'authorization_url' => (string) ($accessData['authorization_url'] ?? ''),
            'access_code' => $accessData['access_code'] ?? null,
            'reference' => (string) ($accessData['reference'] ?? ''),
        ];
    }

    public function charge(array $paymentData): PaymentResult
    {
        $currency = strtoupper($paymentData['currency'] ?? 'NGN');
        $amount = (int) ($paymentData['amount'] ?? 0);
        $email = $paymentData['email'] ?? $paymentData['customer_email'] ?? '';
        $reference = $paymentData['reference'] ?? 'txn_'.Str::random(16);
        $callbackUrl = $paymentData['callback_url'] ?? $paymentData['return_url'] ?? '';

        $body = [
            'amount' => $amount,
            'email' => $email,
            'reference' => $reference,
            'currency' => $currency,
        ];

        if ($callbackUrl) {
            $body['callback_url'] = $callbackUrl;
        }

        if (! empty($paymentData['metadata'])) {
            $body['metadata'] = $paymentData['metadata'];
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/transaction/initialize", $body);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return new PaymentResult(
                transactionId: $reference,
                status: 'failed',
                provider: 'paystack',
                amount: $amount,
                currency: strtolower($currency),
                errorMessage: $data['message'] ?? 'Failed to initialize transaction',
            );
        }

        $accessData = $data['data'] ?? [];
        $authorizationUrl = $accessData['authorization_url'] ?? '';

        return new PaymentResult(
            transactionId: $accessData['reference'] ?? $reference,
            status: 'pending',
            provider: 'paystack',
            amount: $amount,
            currency: strtolower($currency),
            metadata: [
                'authorization_url' => $authorizationUrl,
                'access_code' => $accessData['access_code'] ?? null,
            ],
        );
    }

    public function refund(string $transactionId, ?int $amount = null): PaymentResult
    {
        $body = ['transaction' => $transactionId];
        if ($amount !== null) {
            $body['amount'] = $amount;
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/refund", $body);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return new PaymentResult(
                transactionId: $transactionId,
                status: 'failed',
                provider: 'paystack',
                amount: $amount ?? 0,
                currency: 'ngn',
                errorMessage: $data['message'] ?? 'Refund failed',
            );
        }

        $refundData = $data['data'] ?? [];

        return new PaymentResult(
            transactionId: $refundData['reference'] ?? $transactionId,
            status: ($refundData['status'] ?? 'pending') === 'success' ? 'refunded' : 'pending',
            provider: 'paystack',
            amount: $refundData['amount'] ?? $amount ?? 0,
            currency: 'ngn',
            metadata: $refundData,
        );
    }

    public function getPaymentStatus(string $transactionId): PaymentResult
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/transaction/verify/".$transactionId);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return new PaymentResult(
                transactionId: $transactionId,
                status: 'failed',
                provider: 'paystack',
                amount: 0,
                currency: 'ngn',
                errorMessage: $data['message'] ?? 'Verification failed',
            );
        }

        $tx = $data['data'] ?? [];
        $gatewayResponse = $tx['gateway_response'] ?? '';
        $status = ($tx['status'] ?? '') === 'success' ? 'completed' : 'pending';
        if (strtolower($gatewayResponse) === 'declined' || ($tx['gateway_response'] ?? '') === 'Declined') {
            $status = 'failed';
        }

        return new PaymentResult(
            transactionId: (string) ($tx['id'] ?? $tx['reference'] ?? $transactionId),
            status: $status,
            provider: 'paystack',
            amount: (int) ($tx['amount'] ?? 0),
            currency: strtolower($tx['currency'] ?? 'ngn'),
            customerId: $tx['customer']['customer_code'] ?? null,
            metadata: $tx,
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secretKey = $this->config['secret_key'] ?? '';
        if (! $secretKey) {
            return false;
        }
        $computed = hash_hmac('sha512', $payload, $secretKey);

        return hash_equals($computed, $signature);
    }

    public function getSupportedCurrencies(): array
    {
        return ['ngn', 'ghs', 'zar', 'kes', 'usd', 'xof'];
    }

    public function createSubscription(array $subscriptionData): SubscriptionResult
    {
        $planCode = $subscriptionData['plan_code'] ?? null;
        $customer = $subscriptionData['customer'] ?? $subscriptionData['customer_id'] ?? null;
        $authorizationCode = $subscriptionData['authorization_code'] ?? null;

        if (! $planCode || ! $customer || ! $authorizationCode) {
            return new SubscriptionResult(
                subscriptionId: '',
                status: 'failed',
                provider: 'paystack',
                errorMessage: 'Missing plan_code, customer, or authorization_code',
            );
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/subscription", [
                'plan' => $planCode,
                'customer' => $customer,
                'authorization' => $authorizationCode,
            ]);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return new SubscriptionResult(
                subscriptionId: '',
                status: 'failed',
                provider: 'paystack',
                errorMessage: $data['message'] ?? 'Subscription creation failed',
            );
        }

        $sub = $data['data'] ?? [];

        return new SubscriptionResult(
            subscriptionId: (string) ($sub['subscription_code'] ?? $sub['id'] ?? ''),
            status: $sub['status'] ?? 'active',
            provider: 'paystack',
            customerId: (string) ($sub['customer']['customer_code'] ?? $customer),
            planId: $planCode,
            amount: (int) ($sub['amount'] ?? 0),
            currency: strtolower($sub['plan']['currency'] ?? 'ngn'),
            currentPeriodEnd: isset($sub['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($sub['next_payment_date'])) : null,
            metadata: $sub,
        );
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/subscription/disable", [
                'code' => $subscriptionId,
                'token' => $subscriptionId,
            ]);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'paystack',
                errorMessage: $data['message'] ?? 'Cancel failed',
            );
        }

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: 'canceled',
            provider: 'paystack',
        );
    }

    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->put("{$this->baseUrl}/subscription/{$subscriptionId}", $updates);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'paystack',
                errorMessage: $data['message'] ?? 'Update failed',
            );
        }

        $sub = $data['data'] ?? [];

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: $sub['status'] ?? 'active',
            provider: 'paystack',
            currentPeriodEnd: isset($sub['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($sub['next_payment_date'])) : null,
        );
    }

    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/subscription/{$subscriptionId}");

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'paystack',
                errorMessage: $data['message'] ?? 'Fetch failed',
            );
        }

        $sub = $data['data'] ?? [];

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: $sub['status'] ?? 'unknown',
            provider: 'paystack',
            customerId: (string) ($sub['customer']['customer_code'] ?? ''),
            planId: (string) ($sub['plan']['plan_code'] ?? ''),
            amount: (int) ($sub['amount'] ?? 0),
            currency: strtolower($sub['plan']['currency'] ?? 'ngn'),
            currentPeriodEnd: isset($sub['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($sub['next_payment_date'])) : null,
            metadata: $sub,
        );
    }

    /**
     * Get hosted link for customer to manage subscription (update card, cancel).
     */
    public function getManageSubscriptionLink(string $subscriptionCode): string
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/subscription/{$subscriptionCode}/manage/link");

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new \RuntimeException($data['message'] ?? 'Paystack manage link failed');
        }

        return (string) (($data['data'] ?? [])['link'] ?? '');
    }

    public function getPublicKey(): string
    {
        return $this->config['public_key'] ?? ($this->isTestMode() ? 'pk_test_placeholder' : '');
    }
}
