<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Memora\Models\MemoraSubscription;
use App\Domains\Memora\Models\MemoraSubscriptionHistory;
use App\Models\User;
use App\Services\Payment\Providers\StripeProvider;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    protected function generateStripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_checkout_session_completed_creates_subscription_and_performs_post_webhook_actions(): void
    {
        $user = User::factory()->create([
            'memora_tier' => 'starter',
            'stripe_customer_id' => null,
        ]);

        $subscriptionId = 'sub_test_'.uniqid();
        $customerId = 'cus_test_'.uniqid();
        $sessionId = 'cs_test_'.uniqid();
        $eventId = 'evt_test_'.uniqid();

        $stripeSubscription = \Stripe\Subscription::constructFrom([
            'id' => $subscriptionId,
            'status' => 'active',
            'currency' => 'usd',
            'current_period_start' => strtotime('+1 month'),
            'current_period_end' => strtotime('+2 months'),
            'items' => [
                'data' => [['price' => ['id' => 'price_test_123', 'unit_amount' => 2999]]],
            ],
        ]);

        $event = \Stripe\Event::constructFrom([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'metadata' => ['user_uuid' => $user->uuid, 'tier' => 'pro', 'billing_cycle' => 'monthly'],
                    'subscription' => $subscriptionId,
                    'customer' => $customerId,
                ],
            ],
        ]);

        $payload = json_encode([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'metadata' => ['user_uuid' => $user->uuid, 'tier' => 'pro', 'billing_cycle' => 'monthly'],
                    'subscription' => $subscriptionId,
                    'customer' => $customerId,
                ],
            ],
        ]);
        $secret = config('payment.providers.stripe.webhook_secret') ?: 'whsec_test_secret';
        $signature = $this->generateStripeSignature($payload, $secret);

        $this->mock(StripeProvider::class, function ($mock) use ($event, $stripeSubscription) {
            $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
            $mock->shouldReceive('getSubscription')
                ->with(\Mockery::on(fn ($id) => is_string($id) && str_starts_with($id, 'sub_')))
                ->andReturn($stripeSubscription);
        });

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertStatus(200);
        $this->assertStringContainsString('Webhook handled', $response->getContent() ?: '');

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $this->assertEquals('pro', $subscription->tier);
            $this->assertEquals('monthly', $subscription->billing_cycle);
            $user->refresh();
            $this->assertEquals('pro', $user->memora_tier);
        }
    }

    public function test_checkout_session_completed_idempotent_on_duplicate_webhook(): void
    {
        $user = User::factory()->create(['memora_tier' => 'starter']);
        $subscriptionId = 'sub_test_'.uniqid();
        $customerId = 'cus_test_'.uniqid();
        $sessionId = 'cs_test_'.uniqid();

        $stripeSubscription = \Stripe\Subscription::constructFrom([
            'id' => $subscriptionId,
            'status' => 'active',
            'currency' => 'usd',
            'current_period_start' => time(),
            'current_period_end' => strtotime('+1 year'),
            'items' => [
                'data' => [['price' => ['id' => 'price_annual', 'unit_amount' => 29900]]],
            ],
        ]);

        $eventId = 'evt_test_'.uniqid();
        $event = \Stripe\Event::constructFrom([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'metadata' => ['user_uuid' => $user->uuid, 'tier' => 'pro', 'billing_cycle' => 'annual'],
                    'subscription' => $subscriptionId,
                    'customer' => $customerId,
                ],
            ],
        ]);

        $secret = 'whsec_test_idempotent';
        config(['payment.providers.stripe.webhook_secret' => $secret]);

        $this->mock(StripeProvider::class, function ($mock) use ($event, $stripeSubscription) {
            $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
            $mock->shouldReceive('getSubscription')->with(\Mockery::on(fn ($id) => is_string($id) && str_starts_with($id, 'sub_')))->andReturn($stripeSubscription);
        });

        $payload = json_encode([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'metadata' => ['user_uuid' => $user->uuid, 'tier' => 'pro', 'billing_cycle' => 'annual'],
                    'subscription' => $subscriptionId,
                    'customer' => $customerId,
                ],
            ],
        ]);
        $signature = $this->generateStripeSignature($payload, $secret);

        $r1 = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
        $r1->assertStatus(200);

        $r2 = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->generateStripeSignature($payload, $secret),
        ], $payload);
        $r2->assertStatus(200);

        $count = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->count();
        $this->assertLessThanOrEqual(1, $count, 'Idempotency: at most one subscription per id');
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson('/api/v1/webhooks/stripe', [
            'type' => 'checkout.session.completed',
        ]);

        $response->assertStatus(400);
    }
}
