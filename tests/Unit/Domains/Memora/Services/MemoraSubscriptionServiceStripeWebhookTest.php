<?php

namespace Tests\Unit\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraSubscription;
use App\Domains\Memora\Models\MemoraSubscriptionHistory;
use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Models\User;
use App\Services\Payment\Providers\StripeProvider;
use Mockery;
use Tests\TestCase;

class MemoraSubscriptionServiceStripeWebhookTest extends TestCase
{
    public function test_handle_checkout_completed_creates_subscription_and_performs_actions(): void
    {
        $user = User::factory()->create([
            'memora_tier' => 'starter',
            'stripe_customer_id' => null,
        ]);

        $subscriptionId = 'sub_test_'.uniqid();
        $customerId = 'cus_test_'.uniqid();

        $stripeSubscription = \Stripe\Subscription::constructFrom([
            'id' => $subscriptionId,
            'status' => 'active',
            'currency' => 'usd',
            'current_period_start' => strtotime('+1 month'),
            'current_period_end' => strtotime('+2 months'),
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_test_123', 'unit_amount' => 2999],
                    ],
                ],
            ],
        ]);

        $stripeMock = Mockery::mock(StripeProvider::class);
        $stripeMock->shouldReceive('getSubscription')
            ->with($subscriptionId)
            ->andReturn($stripeSubscription);

        $service = new MemoraSubscriptionService(
            $stripeMock,
            app(\App\Services\Notification\NotificationService::class),
            app(\App\Domains\Memora\Services\EmailNotificationService::class),
            app(\App\Services\Currency\CurrencyService::class),
            app(\App\Services\Storage\UserStorageService::class)
        );

        $data = [
            'id' => 'cs_test_'.uniqid(),
            'metadata' => [
                'user_uuid' => $user->uuid,
                'tier' => 'pro',
                'billing_cycle' => 'monthly',
            ],
            'subscription' => $subscriptionId,
            'customer' => $customerId,
        ];

        $service->handleCheckoutCompleted($data);

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('pro', $subscription->tier);
        $this->assertEquals('monthly', $subscription->billing_cycle);
        $this->assertEquals('active', $subscription->status);

        $user->refresh();
        $this->assertEquals('pro', $user->memora_tier);
        $this->assertEquals($customerId, $user->stripe_customer_id);

        $history = MemoraSubscriptionHistory::where('payment_reference', $subscriptionId)->first();
        $this->assertNotNull($history);
        $this->assertEquals('created', $history->event_type);
        $this->assertEquals('starter', $history->from_tier);
        $this->assertEquals('pro', $history->to_tier);
    }
}
