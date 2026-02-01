<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\SubscriptionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlutterwaveProvider implements PaymentProviderInterface, SubscriptionProviderInterface
{
    protected array $config;

    protected string $baseUrl;

    public function __construct()
    {
        $this->config = config('payment.providers.flutterwave', []);
        $this->config['secret_key'] = isset($this->config['secret_key']) ? trim((string) $this->config['secret_key']) : '';
        $this->config['public_key'] = isset($this->config['public_key']) ? trim((string) $this->config['public_key']) : '';

        if (empty($this->config['test_mode']) && empty($this->config['secret_key'])) {
            throw new \RuntimeException('Flutterwave secret key is not configured. Set FLUTTERWAVE_TEST_MODE=true or PAYMENT_MODE=test to use without keys.');
        }

        $this->baseUrl = $this->config['base_url'] ?? '';
        if ($this->baseUrl === '') {
            throw new \RuntimeException('Flutterwave base_url is not configured. Check config/payment.php.');
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
     * Flutterwave amounts are in main currency unit (e.g. 100 = 100 NGN).
     * Our paymentData amount is in smallest unit (cents/kobo), so we divide by 100.
     */
    protected function toMainUnit(int $amountSmallestUnit): float
    {
        return round($amountSmallestUnit / 100, 2);
    }

    public function charge(array $paymentData): PaymentResult
    {
        $currency = strtoupper($paymentData['currency'] ?? 'NGN');
        $amountSmallest = (int) ($paymentData['amount'] ?? 0);
        $amount = $this->toMainUnit($amountSmallest);
        $email = $paymentData['email'] ?? $paymentData['customer_email'] ?? '';
        $txRef = $paymentData['reference'] ?? $paymentData['tx_ref'] ?? 'txn_'.Str::random(16);
        $redirectUrl = $paymentData['callback_url'] ?? $paymentData['return_url'] ?? '';

        $body = [
            'tx_ref' => $txRef,
            'amount' => (string) $amount,
            'currency' => $currency,
            'redirect_url' => $redirectUrl ?: config('app.url').'/payment/callback',
            'customer' => [
                'email' => $email,
                'name' => $paymentData['customer_name'] ?? $paymentData['name'] ?? 'Customer',
            ],
        ];

        if (! empty($paymentData['phone_number'])) {
            $body['customer']['phonenumber'] = $paymentData['phone_number'];
        }

        if (! empty($paymentData['metadata'])) {
            $body['meta'] = $paymentData['metadata'];
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payments", $body);

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            $apiMessage = $data['message'] ?? $data['error']['message'] ?? $data['data']['message'] ?? null;
            $secretPrefix = str_starts_with((string) ($this->config['secret_key'] ?? ''), 'FLWSECK_TEST') ? 'FLWSECK_TEST' : 'FLWSECK';
            Log::warning('Flutterwave charge failed', [
                'http_status' => $response->status(),
                'base_url' => $this->baseUrl,
                'key_type' => $secretPrefix,
                'response' => $data,
                'tx_ref' => $txRef,
            ]);
            $userMessage = $apiMessage ?? 'Failed to initialize payment';
            if ($response->status() === 401) {
                $userMessage = 'Flutterwave rejected the request. Ensure FLUTTERWAVE_TEST_SECRET_KEY is the exact Secret key from Dashboard → Settings → API Keys (Test). If it still fails, Flutterwave may require OAuth: add FLUTTERWAVE_TEST_CLIENT_ID and FLUTTERWAVE_TEST_CLIENT_SECRET (get them from Dashboard → Switch to v4 API keys, or from developersandbox.flutterwave.com).';
            }
            return new PaymentResult(
                transactionId: $txRef,
                status: 'failed',
                provider: 'flutterwave',
                amount: $amountSmallest,
                currency: strtolower($currency),
                errorMessage: $userMessage,
            );
        }

        $link = $data['data']['link'] ?? '';

        return new PaymentResult(
            transactionId: (string) ($data['data']['id'] ?? $txRef),
            status: 'pending',
            provider: 'flutterwave',
            amount: $amountSmallest,
            currency: strtolower($currency),
            metadata: [
                'link' => $link,
                'tx_ref' => $txRef,
            ],
        );
    }

    public function refund(string $transactionId, ?int $amount = null): PaymentResult
    {
        $body = [];
        if ($amount !== null) {
            $body['amount'] = $this->toMainUnit($amount);
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/transactions/{$transactionId}/refund", $body);

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            return new PaymentResult(
                transactionId: $transactionId,
                status: 'failed',
                provider: 'flutterwave',
                amount: $amount ?? 0,
                currency: 'ngn',
                errorMessage: $data['message'] ?? $data['data']['message'] ?? 'Refund failed',
            );
        }

        $refundData = $data['data'] ?? [];

        return new PaymentResult(
            transactionId: (string) ($refundData['id'] ?? $transactionId),
            status: ($refundData['status'] ?? 'pending') === 'successful' ? 'refunded' : 'pending',
            provider: 'flutterwave',
            amount: isset($refundData['amount']) ? (int) round($refundData['amount'] * 100) : ($amount ?? 0),
            currency: strtolower($refundData['currency'] ?? 'ngn'),
            metadata: $refundData,
        );
    }

    public function getPaymentStatus(string $transactionId): PaymentResult
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/transactions/{$transactionId}/verify");

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            return new PaymentResult(
                transactionId: $transactionId,
                status: 'failed',
                provider: 'flutterwave',
                amount: 0,
                currency: 'ngn',
                errorMessage: $data['message'] ?? 'Verification failed',
            );
        }

        $tx = $data['data'] ?? [];
        $status = strtolower($tx['status'] ?? '') === 'successful' ? 'completed' : 'pending';
        $amount = (int) round(($tx['amount'] ?? 0) * 100);

        return new PaymentResult(
            transactionId: (string) ($tx['id'] ?? $transactionId),
            status: $status,
            provider: 'flutterwave',
            amount: $amount,
            currency: strtolower($tx['currency'] ?? 'ngn'),
            customerId: isset($tx['customer']) ? (string) ($tx['customer']['id'] ?? '') : null,
            metadata: $tx,
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secretHash = $this->config['secret_hash'] ?? '';
        if (! $secretHash && ! $this->isTestMode()) {
            return false;
        }
        if ($this->isTestMode() && ! $secretHash) {
            return true;
        }

        if ($signature !== '' && preg_match('/^[A-Za-z0-9+\/=]+$/', $signature)) {
            $computed = base64_encode(hash_hmac('sha256', $payload, $secretHash, true));
            return hash_equals($computed, $signature);
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return false;
        }

        $data = $decoded['data'] ?? $decoded;
        $txId = (string) ($data['id'] ?? $data['tx_id'] ?? '');
        $txRef = (string) ($data['tx_ref'] ?? $data['reference'] ?? '');
        $incomingHash = $data['verif_hash'] ?? $signature;

        $computed = hash('sha256', $txId.$secretHash.$txRef);

        return hash_equals($computed, $incomingHash);
    }

    public function getSupportedCurrencies(): array
    {
        return ['ngn', 'ghs', 'zar', 'kes', 'ugx', 'tzs', 'rwf', 'usd'];
    }

    public function createSubscription(array $subscriptionData): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/subscriptions", [
                'amount' => $this->toMainUnit($subscriptionData['amount'] ?? 0),
                'currency' => strtoupper($subscriptionData['currency'] ?? 'NGN'),
                'plan' => $subscriptionData['plan_id'] ?? $subscriptionData['plan'] ?? '',
                'customer' => $subscriptionData['customer_id'] ?? $subscriptionData['customer'] ?? '',
            ]);

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            return new SubscriptionResult(
                subscriptionId: '',
                status: 'failed',
                provider: 'flutterwave',
                errorMessage: $data['message'] ?? $data['data']['message'] ?? 'Subscription creation failed',
            );
        }

        $sub = $data['data'] ?? [];

        return new SubscriptionResult(
            subscriptionId: (string) ($sub['id'] ?? $sub['subscription_id'] ?? ''),
            status: $sub['status'] ?? 'active',
            provider: 'flutterwave',
            customerId: (string) ($sub['customer_id'] ?? ''),
            planId: (string) ($sub['plan_id'] ?? ''),
            amount: (int) ($subscriptionData['amount'] ?? 0),
            currency: strtolower($sub['currency'] ?? 'ngn'),
            currentPeriodEnd: isset($sub['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($sub['next_payment_date'])) : null,
            metadata: $sub,
        );
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/subscriptions/{$subscriptionId}/cancel");

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'flutterwave',
                errorMessage: $data['message'] ?? 'Cancel failed',
            );
        }

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: 'canceled',
            provider: 'flutterwave',
        );
    }

    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->put("{$this->baseUrl}/subscriptions/{$subscriptionId}", $updates);

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'flutterwave',
                errorMessage: $data['message'] ?? 'Update failed',
            );
        }

        $sub = $data['data'] ?? [];

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: $sub['status'] ?? 'active',
            provider: 'flutterwave',
            currentPeriodEnd: isset($sub['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($sub['next_payment_date'])) : null,
        );
    }

    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/subscriptions/{$subscriptionId}");

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'flutterwave',
                errorMessage: $data['message'] ?? 'Fetch failed',
            );
        }

        $sub = $data['data'] ?? [];

        return new SubscriptionResult(
            subscriptionId: $subscriptionId,
            status: $sub['status'] ?? 'unknown',
            provider: 'flutterwave',
            customerId: (string) ($sub['customer_id'] ?? ''),
            planId: (string) ($sub['plan_id'] ?? ''),
            amount: isset($sub['amount']) ? (int) round($sub['amount'] * 100) : null,
            currency: strtolower($sub['currency'] ?? 'ngn'),
            currentPeriodEnd: isset($sub['next_payment_date']) ? date('Y-m-d H:i:s', strtotime($sub['next_payment_date'])) : null,
            metadata: $sub,
        );
    }

    public function getPublicKey(): string
    {
        return $this->config['public_key'] ?? ($this->isTestMode() ? 'pk_test_placeholder' : '');
    }
}
