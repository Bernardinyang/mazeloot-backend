<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Contracts\SubscriptionProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\DTOs\SubscriptionResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use Stripe\BillingPortal\Session as PortalSession;

class StripeProvider implements PaymentProviderInterface, SubscriptionProviderInterface
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('payment.providers.stripe', []);

        if (empty($this->config['test_mode']) && empty($this->config['secret_key'])) {
            throw new \RuntimeException('Stripe secret key is not configured. Set STRIPE_TEST_MODE=true to use without keys.');
        }

        if (empty($this->config['test_mode'])) {
            Stripe::setApiKey($this->config['secret_key']);
        }
    }

    protected function isTestMode(): bool
    {
        return ! empty($this->config['test_mode']);
    }

    public function charge(array $paymentData): PaymentResult
    {
        if ($this->isTestMode()) {
            return new PaymentResult(
                transactionId: 'pi_test_'.Str::random(14),
                status: 'completed',
                provider: 'stripe',
                amount: $paymentData['amount'] ?? 0,
                currency: $paymentData['currency'] ?? 'usd',
                metadata: ['test_mode' => true],
            );
        }

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'usd',
                'customer' => $paymentData['customer_id'] ?? null,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'confirmation_method' => 'automatic',
                'confirm' => true,
                'metadata' => $paymentData['metadata'] ?? [],
                'description' => $paymentData['description'] ?? null,
                'return_url' => $paymentData['return_url'] ?? null,
            ]);

            return new PaymentResult(
                transactionId: $paymentIntent->id,
                status: $paymentIntent->status,
                provider: 'stripe',
                amount: $paymentIntent->amount,
                currency: $paymentIntent->currency,
                metadata: $paymentIntent->metadata->toArray(),
            );
        } catch (ApiErrorException $e) {
            return new PaymentResult(
                transactionId: '',
                status: 'failed',
                provider: 'stripe',
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function refund(string $transactionId, ?int $amount = null): PaymentResult
    {
        if ($this->isTestMode()) {
            return new PaymentResult(
                transactionId: 're_test_'.Str::random(14),
                status: 'refunded',
                provider: 'stripe',
                amount: $amount ?? 0,
                currency: 'usd',
            );
        }

        try {
            $refundData = ['payment_intent' => $transactionId];
            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }

            $refund = Refund::create($refundData);

            return new PaymentResult(
                transactionId: $refund->id,
                status: $refund->status,
                provider: 'stripe',
                amount: $refund->amount,
                currency: $refund->currency,
            );
        } catch (ApiErrorException $e) {
            return new PaymentResult(
                transactionId: $transactionId,
                status: 'failed',
                provider: 'stripe',
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function getPaymentStatus(string $transactionId): PaymentResult
    {
        if ($this->isTestMode()) {
            return new PaymentResult(
                transactionId: $transactionId,
                status: str_starts_with($transactionId, 'pi_test_') ? 'completed' : 'pending',
                provider: 'stripe',
                amount: 0,
                currency: 'usd',
            );
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($transactionId);

            return new PaymentResult(
                transactionId: $paymentIntent->id,
                status: $paymentIntent->status,
                provider: 'stripe',
                amount: $paymentIntent->amount,
                currency: $paymentIntent->currency,
                metadata: $paymentIntent->metadata->toArray(),
            );
        } catch (ApiErrorException $e) {
            return new PaymentResult(
                transactionId: $transactionId,
                status: 'failed',
                provider: 'stripe',
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if ($this->isTestMode()) {
            return true;
        }

        $webhookSecret = $this->config['webhook_secret'] ?? null;
        if (!$webhookSecret) {
            return false;
        }

        try {
            Webhook::constructEvent($payload, $signature, $webhookSecret);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        if ($this->isTestMode()) {
            throw new \RuntimeException('Webhooks are not supported in Stripe test mode');
        }

        return Webhook::constructEvent(
            $payload,
            $signature,
            $this->config['webhook_secret']
        );
    }

    public function getSupportedCurrencies(): array
    {
        return ['usd', 'eur', 'gbp', 'cad', 'aud', 'jpy', 'ngn', 'zar', 'kes', 'ghs'];
    }

    /**
     * Create or retrieve a Stripe customer
     *
     * @return Customer|object
     */
    public function getOrCreateCustomer(string $email, ?string $customerId = null, array $metadata = [])
    {
        if ($this->isTestMode()) {
            $mock = new \stdClass;
            $mock->id = $customerId ?: 'cus_test_'.Str::random(14);

            return $mock;
        }

        if ($customerId) {
            try {
                return Customer::retrieve($customerId);
            } catch (ApiErrorException $e) {
                // Customer not found, create new one
            }
        }

        return Customer::create([
            'email' => $email,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a Stripe Checkout Session for subscription
     *
     * @return Session|object
     */
    public function createCheckoutSession(array $data)
    {
        if ($this->isTestMode()) {
            $sessionId = 'cs_test_'.Str::random(24);
            $metadata = $data['metadata'] ?? [];
            Cache::put("test_checkout:{$sessionId}", $metadata, 3600);

            $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
            $successUrl = "{$frontendUrl}/subscription/success?test=1&session_id={$sessionId}";

            $mock = new \stdClass;
            $mock->id = $sessionId;
            $mock->url = $successUrl;

            return $mock;
        }

        return Session::create([
            'customer' => $data['customer'] ?? $data['customer_id'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'mode' => $data['mode'] ?? 'subscription',
            'line_items' => $data['line_items'],
            'success_url' => $data['success_url'] ?? null,
            'cancel_url' => $data['cancel_url'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'subscription_data' => $data['subscription_data'] ?? null,
            'allow_promotion_codes' => $data['allow_promotion_codes'] ?? true,
        ]);
    }

    /**
     * Create a billing portal session
     *
     * @return PortalSession|object
     */
    public function createPortalSession(string $customerId, string $returnUrl)
    {
        if ($this->isTestMode()) {
            $mock = new \stdClass;
            $mock->url = $returnUrl;

            return $mock;
        }

        return PortalSession::create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    public function createSubscription(array $subscriptionData): SubscriptionResult
    {
        if ($this->isTestMode()) {
            return new SubscriptionResult(
                subscriptionId: 'sub_test_'.Str::random(14),
                status: 'active',
                provider: 'stripe',
                customerId: $subscriptionData['customer_id'] ?? null,
                planId: $subscriptionData['price_id'] ?? null,
                amount: $subscriptionData['amount'] ?? null,
                currency: $subscriptionData['currency'] ?? 'usd',
                currentPeriodEnd: now()->addMonth()->toDateTimeString(),
                metadata: ['test_mode' => true],
            );
        }

        try {
            $subscription = Subscription::create([
                'customer' => $subscriptionData['customer_id'],
                'items' => [['price' => $subscriptionData['price_id']]],
                'metadata' => $subscriptionData['metadata'] ?? [],
                'trial_period_days' => $subscriptionData['trial_days'] ?? null,
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            return new SubscriptionResult(
                subscriptionId: $subscription->id,
                status: $subscription->status,
                provider: 'stripe',
                customerId: $subscription->customer,
                planId: $subscriptionData['price_id'],
                amount: $subscription->items->data[0]->price->unit_amount ?? null,
                currency: $subscription->currency,
                currentPeriodEnd: date('Y-m-d H:i:s', $subscription->current_period_end),
                metadata: $subscription->metadata->toArray(),
            );
        } catch (ApiErrorException $e) {
            return new SubscriptionResult(
                subscriptionId: '',
                status: 'failed',
                provider: 'stripe',
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        if ($this->isTestMode()) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'canceled',
                provider: 'stripe',
                currentPeriodEnd: now()->addMonth()->toDateTimeString(),
            );
        }

        try {
            $subscription = Subscription::update($subscriptionId, [
                'cancel_at_period_end' => true,
            ]);

            return new SubscriptionResult(
                subscriptionId: $subscription->id,
                status: $subscription->status,
                provider: 'stripe',
                customerId: $subscription->customer,
                currentPeriodEnd: date('Y-m-d H:i:s', $subscription->current_period_end),
            );
        } catch (ApiErrorException $e) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'stripe',
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult
    {
        if ($this->isTestMode()) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'active',
                provider: 'stripe',
                currentPeriodEnd: now()->addMonth()->toDateTimeString(),
            );
        }

        try {
            $subscription = Subscription::update($subscriptionId, $updates);

            return new SubscriptionResult(
                subscriptionId: $subscription->id,
                status: $subscription->status,
                provider: 'stripe',
                customerId: $subscription->customer,
                currentPeriodEnd: date('Y-m-d H:i:s', $subscription->current_period_end),
            );
        } catch (ApiErrorException $e) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'stripe',
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult
    {
        if ($this->isTestMode()) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: str_starts_with($subscriptionId, 'sub_test_') ? 'active' : 'unknown',
                provider: 'stripe',
                currentPeriodEnd: now()->addMonth()->toDateTimeString(),
            );
        }

        try {
            $subscription = Subscription::retrieve($subscriptionId);

            return new SubscriptionResult(
                subscriptionId: $subscription->id,
                status: $subscription->status,
                provider: 'stripe',
                customerId: $subscription->customer,
                planId: $subscription->items->data[0]->price->id ?? null,
                amount: $subscription->items->data[0]->price->unit_amount ?? null,
                currency: $subscription->currency,
                currentPeriodEnd: date('Y-m-d H:i:s', $subscription->current_period_end),
                metadata: $subscription->metadata->toArray(),
            );
        } catch (ApiErrorException $e) {
            return new SubscriptionResult(
                subscriptionId: $subscriptionId,
                status: 'failed',
                provider: 'stripe',
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function getSubscription(string $subscriptionId): Subscription
    {
        if ($this->isTestMode()) {
            throw new \RuntimeException('getSubscription is not available in Stripe test mode');
        }

        return Subscription::retrieve($subscriptionId);
    }

    public function getPublicKey(): string
    {
        return $this->config['public_key'] ?? ($this->isTestMode() ? 'pk_test_placeholder' : '');
    }
}
