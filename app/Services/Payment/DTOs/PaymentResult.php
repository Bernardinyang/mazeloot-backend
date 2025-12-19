<?php

namespace App\Services\Payment\DTOs;

class PaymentResult
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $status, // pending, completed, failed, refunded
        public readonly string $provider,
        public readonly int $amount, // in smallest currency unit
        public readonly string $currency,
        public readonly ?string $customerId = null,
        public readonly ?array $metadata = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public function toArray(): array
    {
        return [
            'transactionId' => $this->transactionId,
            'status' => $this->status,
            'provider' => $this->provider,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customerId' => $this->customerId,
            'metadata' => $this->metadata,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
