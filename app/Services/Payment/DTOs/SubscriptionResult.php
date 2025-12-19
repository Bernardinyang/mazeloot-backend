<?php

namespace App\Services\Payment\DTOs;

class SubscriptionResult
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $status, // active, cancelled, past_due, etc.
        public readonly string $provider,
        public readonly ?string $customerId = null,
        public readonly ?string $planId = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $currentPeriodEnd = null,
        public readonly ?array $metadata = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public function toArray(): array
    {
        return [
            'subscriptionId' => $this->subscriptionId,
            'status' => $this->status,
            'provider' => $this->provider,
            'customerId' => $this->customerId,
            'planId' => $this->planId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'currentPeriodEnd' => $this->currentPeriodEnd,
            'metadata' => $this->metadata,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
