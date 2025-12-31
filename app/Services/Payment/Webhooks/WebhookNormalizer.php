<?php

namespace App\Services\Payment\Webhooks;

class WebhookNormalizer
{
    /**
     * Normalize webhook payload from any provider to internal format
     *
     * @param  array  $payload  Raw webhook payload
     * @param  string  $provider  Provider name
     * @return array Normalized webhook data
     */
    public function normalize(array $payload, string $provider): array
    {
        return match ($provider) {
            'stripe' => $this->normalizeStripe($payload),
            'paypal' => $this->normalizePayPal($payload),
            'paystack' => $this->normalizePaystack($payload),
            'flutterwave' => $this->normalizeFlutterwave($payload),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    protected function normalizeStripe(array $payload): array
    {
        // Normalize Stripe webhook to internal format
        $eventType = $payload['type'] ?? 'unknown';
        $data = $payload['data']['object'] ?? [];

        return [
            'event_type' => $this->mapStripeEventType($eventType),
            'transaction_id' => $data['id'] ?? null,
            'status' => $this->mapStripeStatus($data['status'] ?? null),
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'raw_payload' => $payload,
        ];
    }

    protected function normalizePayPal(array $payload): array
    {
        // Normalize PayPal webhook to internal format
        $eventType = $payload['event_type'] ?? 'unknown';

        return [
            'event_type' => $this->mapPayPalEventType($eventType),
            'transaction_id' => $payload['resource']['id'] ?? null,
            'status' => $payload['resource']['status'] ?? null,
            'amount' => $payload['resource']['amount']['total'] ?? null,
            'currency' => $payload['resource']['amount']['currency'] ?? null,
            'metadata' => $payload['resource'] ?? [],
            'raw_payload' => $payload,
        ];
    }

    protected function normalizePaystack(array $payload): array
    {
        // Normalize Paystack webhook to internal format
        $event = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        return [
            'event_type' => $this->mapPaystackEventType($event),
            'transaction_id' => $data['reference'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'raw_payload' => $payload,
        ];
    }

    protected function normalizeFlutterwave(array $payload): array
    {
        // Normalize Flutterwave webhook to internal format
        $event = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        return [
            'event_type' => $this->mapFlutterwaveEventType($event),
            'transaction_id' => $data['tx_ref'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'metadata' => $data['meta'] ?? [],
            'raw_payload' => $payload,
        ];
    }

    protected function mapStripeEventType(string $type): string
    {
        return match ($type) {
            'payment_intent.succeeded' => 'payment.completed',
            'payment_intent.payment_failed' => 'payment.failed',
            'charge.refunded' => 'payment.refunded',
            default => $type,
        };
    }

    protected function mapStripeStatus(?string $status): ?string
    {
        return match ($status) {
            'succeeded' => 'completed',
            'pending' => 'pending',
            'failed' => 'failed',
            'canceled' => 'cancelled',
            default => $status,
        };
    }

    protected function mapPayPalEventType(string $type): string
    {
        return match ($type) {
            'PAYMENT.CAPTURE.COMPLETED' => 'payment.completed',
            'PAYMENT.CAPTURE.DENIED' => 'payment.failed',
            'PAYMENT.CAPTURE.REFUNDED' => 'payment.refunded',
            default => $type,
        };
    }

    protected function mapPaystackEventType(string $type): string
    {
        return match ($type) {
            'charge.success' => 'payment.completed',
            'charge.failed' => 'payment.failed',
            default => $type,
        };
    }

    protected function mapFlutterwaveEventType(string $type): string
    {
        return match ($type) {
            'charge.completed' => 'payment.completed',
            'charge.failed' => 'payment.failed',
            default => $type,
        };
    }
}
