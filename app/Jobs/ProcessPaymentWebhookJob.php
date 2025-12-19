<?php

namespace App\Jobs;

use App\Services\Payment\Webhooks\WebhookHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [60, 120, 300, 600, 1800]; // 1min, 2min, 5min, 10min, 30min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $provider,
        public array $payload,
        public ?string $signature = null
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookHandler $webhookHandler): void
    {
        try {
            // WebhookHandler::handle expects (array $payload, string $provider)
            // Signature verification should be done before queuing
            $webhookHandler->handle($this->payload, $this->provider);
            
            Log::info("Payment webhook processed successfully", [
                'provider' => $this->provider,
                'payload_keys' => array_keys($this->payload),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to process payment webhook", [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($this->payload),
            ]);
            
            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}

