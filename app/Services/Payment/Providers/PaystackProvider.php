<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\SubscriptionResult;

class PaystackProvider implements PaymentProviderInterface, SubscriptionProviderInterface
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('payment.providers.paystack', []);
    }

    public function charge(array $paymentData): PaymentResult
    {
        throw new \RuntimeException('Paystack provider not yet fully implemented. Install yabacon/paystack-php package.');
    }

    public function refund(string $transactionId, ?int $amount = null): PaymentResult
    {
        throw new \RuntimeException('Paystack refund not yet fully implemented');
    }

    public function getPaymentStatus(string $transactionId): PaymentResult
    {
        throw new \RuntimeException('Paystack payment status not yet fully implemented');
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // Paystack webhook signature verification
        $secretKey = $this->config['secret_key'] ?? null;
        if (! $secretKey) {
            return false;
        }

        // Paystack webhook verification logic
        return true; // Placeholder
    }

    public function getSupportedCurrencies(): array
    {
        return ['ngn', 'ghs', 'zar', 'kes'];
    }

    public function createSubscription(array $subscriptionData): SubscriptionResult
    {
        throw new \RuntimeException('Paystack subscription creation not yet fully implemented');
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        throw new \RuntimeException('Paystack subscription cancellation not yet fully implemented');
    }

    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult
    {
        throw new \RuntimeException('Paystack subscription update not yet fully implemented');
    }

    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult
    {
        throw new \RuntimeException('Paystack subscription status not yet fully implemented');
    }
}
