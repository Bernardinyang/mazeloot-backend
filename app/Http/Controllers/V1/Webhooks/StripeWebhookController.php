<?php

namespace App\Http\Controllers\V1\Webhooks;

use App\Domains\Memora\Models\MemoraSubscription;
use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Services\Payment\Providers\StripeProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeProvider $stripe,
        protected MemoraSubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            Log::warning('Stripe webhook: Missing signature');

            return response('Missing signature', 400);
        }

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                'customer.subscription.created' => $this->handleSubscriptionCreated($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event->data->object),
                'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
                default => Log::info('Stripe webhook: Unhandled event type', ['type' => $event->type]),
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Error handling event', [
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            // Return 200 to prevent Stripe from retrying
            return response('Error logged', 200);
        }

        return response('Webhook handled', 200);
    }

    protected function handleCheckoutCompleted($session): void
    {
        Log::info('Stripe webhook: Checkout completed', ['session_id' => $session->id]);

        $this->subscriptionService->handleCheckoutCompleted([
            'metadata' => $session->metadata?->toArray() ?? [],
            'subscription' => $session->subscription,
            'customer' => $session->customer,
        ]);
    }

    protected function handleSubscriptionCreated($subscription): void
    {
        Log::info('Stripe webhook: Subscription created', ['subscription_id' => $subscription->id]);
        // Handled by checkout.session.completed
    }

    protected function handleSubscriptionUpdated($subscription): void
    {
        Log::info('Stripe webhook: Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        $this->subscriptionService->handleSubscriptionUpdated([
            'id' => $subscription->id,
            'status' => $subscription->status,
            'current_period_start' => $subscription->current_period_start,
            'current_period_end' => $subscription->current_period_end,
            'canceled_at' => $subscription->canceled_at,
        ]);
    }

    protected function handleSubscriptionDeleted($subscription): void
    {
        Log::info('Stripe webhook: Subscription deleted', ['subscription_id' => $subscription->id]);

        $this->subscriptionService->handleSubscriptionDeleted([
            'id' => $subscription->id,
        ]);
    }

    protected function handlePaymentSucceeded($invoice): void
    {
        Log::info('Stripe webhook: Payment succeeded', [
            'invoice_id' => $invoice->id,
            'subscription' => $invoice->subscription,
        ]);

        $billingReason = $invoice->billing_reason ?? null;
        if ($billingReason !== 'subscription_cycle') {
            return;
        }

        $subscriptionId = $invoice->subscription ?? null;
        if (! $subscriptionId) {
            return;
        }

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $user = $subscription->user;
        if ($user) {
            $this->subscriptionService->notifySubscriptionRenewed(
                $user,
                $subscription->tier,
                $subscription->billing_cycle,
                $subscription->current_period_end
            );
        }
    }

    protected function handlePaymentFailed($invoice): void
    {
        Log::warning('Stripe webhook: Payment failed', [
            'invoice_id' => $invoice->id,
            'subscription' => $invoice->subscription,
        ]);

        $subscriptionId = $invoice->subscription ?? null;
        if (! $subscriptionId) {
            return;
        }

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $user = $subscription->user;
        if ($user) {
            $reason = isset($invoice->last_payment_error) ? ($invoice->last_payment_error->message ?? null) : null;
            $this->subscriptionService->notifyPaymentFailed($user, $reason);
        }
    }
}
