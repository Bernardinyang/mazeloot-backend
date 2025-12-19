<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\SubscriptionResult;
use App\Services\Currency\CurrencyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PaymentService
{
    protected PaymentProviderInterface $provider;
    protected CurrencyService $currencyService;

    public function __construct(PaymentProviderInterface $provider, CurrencyService $currencyService)
    {
        $this->provider = $provider;
        $this->currencyService = $currencyService;
    }

    /**
     * Process a payment with automatic provider selection
     *
     * @param array $paymentData
     * @param string|null $provider Force a specific provider
     * @return PaymentResult
     */
    public function charge(array $paymentData, ?string $provider = null): PaymentResult
    {
        // Use provided provider or select based on currency/country
        if ($provider) {
            // Provider already selected via dependency injection
        } else {
            // Provider selected via GlobalServicesProvider based on config
        }

        // Handle idempotency
        $idempotencyKey = $paymentData['idempotency_key'] ?? Str::uuid()->toString();

        // Check if payment was already processed
        $cacheKey = "payment:idempotency:{$idempotencyKey}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Convert amount to smallest currency unit
        $amount = $paymentData['amount'];
        if (!isset($paymentData['amount_in_smallest_unit']) || !$paymentData['amount_in_smallest_unit']) {
            $amount = $this->currencyService->toSmallestUnit($amount, $paymentData['currency']);
        }

        $paymentData['amount'] = $amount;

        // Process payment
        $result = $this->provider->charge($paymentData);

        // Cache result for idempotency (cache for 24 hours)
        Cache::put($cacheKey, $result, now()->addHours(24));

        return $result;
    }

    /**
     * Refund a payment
     *
     * @param string $transactionId
     * @param int|null $amount Amount in smallest currency unit
     * @return PaymentResult
     */
    public function refund(string $transactionId, ?int $amount = null): PaymentResult
    {
        return $this->provider->refund($transactionId, $amount);
    }

    /**
     * Get payment status
     *
     * @param string $transactionId
     * @return PaymentResult
     */
    public function getPaymentStatus(string $transactionId): PaymentResult
    {
        return $this->provider->getPaymentStatus($transactionId);
    }

    /**
     * Create a subscription
     *
     * @param array $subscriptionData
     * @return SubscriptionResult
     */
    public function createSubscription(array $subscriptionData): SubscriptionResult
    {
        if (!$this->provider instanceof SubscriptionProviderInterface) {
            throw new \RuntimeException('Current payment provider does not support subscriptions');
        }

        return $this->provider->createSubscription($subscriptionData);
    }

    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId
     * @return SubscriptionResult
     */
    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        if (!$this->provider instanceof SubscriptionProviderInterface) {
            throw new \RuntimeException('Current payment provider does not support subscriptions');
        }

        return $this->provider->cancelSubscription($subscriptionId);
    }

    /**
     * Select provider based on currency and country
     *
     * @param string $currency
     * @param string|null $country
     * @return string Provider name
     */
    public static function selectProvider(string $currency, ?string $country = null): string
    {
        $config = config('payment.provider_selection', []);

        // Check country-based selection
        if ($country && isset($config['by_country'][$country])) {
            return $config['by_country'][$country];
        }

        // Check currency-based selection
        if (isset($config['by_currency'][$currency])) {
            return $config['by_currency'][$currency];
        }

        // Default provider
        return config('payment.default_provider', 'stripe');
    }
}
