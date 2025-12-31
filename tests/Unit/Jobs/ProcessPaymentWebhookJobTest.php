<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessPaymentWebhookJob;
use App\Services\Payment\Webhooks\WebhookHandler;
use Mockery;
use Tests\TestCase;

class ProcessPaymentWebhookJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_calls_webhook_handler_handle(): void
    {
        $provider = 'stripe';
        $payload = ['event' => 'payment.succeeded', 'data' => ['id' => 'txn_123']];
        $signature = 'test-signature';

        $mockHandler = Mockery::mock(WebhookHandler::class);

        // WebhookHandler::handle expects (array $payload, string $provider)
        $mockHandler
            ->shouldReceive('handle')
            ->once()
            ->with(Mockery::type('array'), $provider);

        $job = new ProcessPaymentWebhookJob($provider, $payload, $signature);
        $job->handle($mockHandler);

        $this->assertTrue(true); // Job executed without errors
    }
}
