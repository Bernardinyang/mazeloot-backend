<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\SubscriptionResult;

class FlutterwaveProvider implements PaymentProviderInterface, SubscriptionProviderInterface
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('payment.providers.flutterwave', []);
    }

    public function charge(array $paymentData): PaymentResult
    {
        throw new \RuntimeException('Flutterwave provider not yet fully implemented. Install flutterwavedev/flutterwave-v3 package.');
    }

    public function refund(string $transactionId, ?int $amount = null): PaymentResult
    {
        throw new \RuntimeException('Flutterwave refund not yet fully implemented');
    }

    public function getPaymentStatus(string $transactionId): PaymentResult
    {
        throw new \RuntimeException('Flutterwave payment status not yet fully implemented');
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // Flutterwave webhook signature verification
        $secretHash = $this->config['secret_hash'] ?? null;
        if (!$secretHash) {
            return false;
        }

        // Flutterwave webhook verification logic
        return true; // Placeholder
    }

    public function getSupportedCurrencies(): array
    {
        return ['ngn', 'ghs', 'zar', 'kes', 'ugx', 'tzs', 'rwf'];
    }

    public function createSubscription(array $subscriptionData): SubscriptionResult
    {
        throw new \RuntimeException('Flutterwave subscription creation not yet fully implemented');
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        throw new \RuntimeException('Flutterwave subscription cancellation not yet fully implemented');
    }

    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult
    {
        throw new \RuntimeException('Flutterwave subscription update not yet fully implemented');
    }

    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult
    {
        throw new \RuntimeException('Flutterwave subscription status not yet fully implemented');
    }
}
