<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\DTOs\PaymentResult;

interface PaymentProviderInterface
{
    /**
     * Process a one-time payment
     *
     * @param array $paymentData Amount, currency, description, customer info, etc.
     * @return PaymentResult
     */
    public function charge(array $paymentData): PaymentResult;

    /**
     * Refund a payment
     *
     * @param string $transactionId
     * @param int|null $amount Amount in smallest currency unit (null for full refund)
     * @return PaymentResult
     */
    public function refund(string $transactionId, ?int $amount = null): PaymentResult;

    /**
     * Get payment status
     *
     * @param string $transactionId
     * @return PaymentResult
     */
    public function getPaymentStatus(string $transactionId): PaymentResult;

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;

    /**
     * Get supported currencies for this provider
     *
     * @return array<string>
     */
    public function getSupportedCurrencies(): array;
}
