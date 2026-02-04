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

        Log::info('Stripe webhook: request received', [
            'payload_length' => strlen($payload),
            'has_signature' => ! empty($signature),
            'content_type' => $request->header('content-type'),
        ]);

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

        $ctx = ['type' => $event->type, 'id' => $event->id ?? null];
        Log::info('Stripe webhook received', $ctx);

        try {
            $handled = false;
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    $handled = true;
                    break;
                case 'customer.subscription.created':
                    Log::info('Stripe webhook: Subscription created (handled by checkout.session.completed)', ['subscription_id' => $event->data->object->id ?? null]);
                    $handled = true;
                    break;
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    $handled = true;
                    break;
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    $handled = true;
                    break;
                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    $handled = true;
                    break;
                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    $handled = true;
                    break;
                default:
                    Log::info('Stripe webhook: Unhandled event type', $ctx);
            }
            if ($handled) {
                Log::info('Stripe webhook: handler completed', ['event' => $event->type]);
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: Error handling event', array_merge($ctx, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));

            return response('Error logged', 200);
        }

        return response('Webhook handled', 200);
    }

    protected function handleCheckoutCompleted($session): void
    {
        $subscriptionId = is_object($session->subscription) ? ($session->subscription->id ?? null) : $session->subscription;
        $customerId = is_object($session->customer) ? ($session->customer->id ?? null) : $session->customer;

        Log::info('Stripe webhook: Checkout completed', ['session_id' => $session->id, 'subscription_id' => $subscriptionId]);

        $this->subscriptionService->handleCheckoutCompleted([
            'id' => $session->id,
            'metadata' => $session->metadata?->toArray() ?? [],
            'subscription' => $subscriptionId,
            'customer' => $customerId,
        ]);
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
        $subscriptionId = is_object($invoice->subscription ?? null) ? ($invoice->subscription->id ?? null) : ($invoice->subscription ?? null);

        Log::info('Stripe webhook: Payment succeeded', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscriptionId,
        ]);

        $billingReason = $invoice->billing_reason ?? null;
        if ($billingReason !== 'subscription_cycle') {
            return;
        }

        if (! $subscriptionId) {
            return;
        }

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->where('payment_provider', 'stripe')->first();
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
        $subscriptionId = is_object($invoice->subscription ?? null) ? ($invoice->subscription->id ?? null) : ($invoice->subscription ?? null);

        Log::warning('Stripe webhook: Payment failed', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscriptionId,
        ]);

        if (! $subscriptionId) {
            return;
        }

        $subscription = MemoraSubscription::where('stripe_subscription_id', $subscriptionId)->where('payment_provider', 'stripe')->first();
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
