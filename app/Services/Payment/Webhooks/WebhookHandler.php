<?php

namespace App\Services\Payment\Webhooks;

use Illuminate\Support\Facades\Event;

class WebhookHandler
{
    protected WebhookNormalizer $normalizer;

    public function __construct(WebhookNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * Handle incoming webhook from payment provider
     *
     * @param array $payload
     * @param string $provider
     * @return void
     */
    public function handle(array $payload, string $provider): void
    {
        // Normalize webhook to internal format
        $normalized = $this->normalizer->normalize($payload, $provider);

        // Dispatch internal event
        Event::dispatch('payment.webhook.' . $normalized['event_type'], $normalized);

        // Also dispatch generic webhook event
        Event::dispatch('payment.webhook.received', [
            'provider' => $provider,
            'normalized' => $normalized,
            'raw' => $payload,
        ]);
    }
}
