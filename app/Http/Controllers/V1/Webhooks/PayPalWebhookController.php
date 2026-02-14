<?php

namespace App\Http\Controllers\V1\Webhooks;

use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Payment\Providers\PayPalProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function __construct(
        protected PayPalProvider $paypal,
        protected MemoraSubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();

        Log::info('PayPal webhook: request received', [
            'payload_length' => strlen($payload),
            'content_type' => $request->header('content-type'),
        ]);

        if ($payload === '') {
            Log::warning('PayPal webhook: empty body - ensure no middleware consumes request body before webhook');
            WebhookEvent::record('paypal', 'failed', 400, null, null, 'Empty payload');

            return response('Empty payload', 400);
        }

        if (! $this->paypal->verifyWebhook($request)) {
            Log::warning('PayPal webhook: Invalid signature', ['payload_length' => strlen($payload)]);
            WebhookEvent::record('paypal', 'failed', 400, null, null, 'Invalid signature');

            return response('Invalid signature', 400);
        }

        $body = json_decode($payload, true);
        if (! is_array($body)) {
            Log::warning('PayPal webhook: Invalid payload (non-array JSON)', ['payload_preview' => substr($payload, 0, 200)]);
            WebhookEvent::record('paypal', 'failed', 400, null, null, 'Invalid payload');

            return response('Invalid payload', 400);
        }

        $eventType = $body['event_type'] ?? '';
        $resource = $body['resource'] ?? [];

        $ctx = [
            'event_type' => $eventType,
            'resource_id' => $resource['id'] ?? null,
        ];

        Log::info('PayPal webhook received', $ctx);

        try {
            $handled = false;
            switch ($eventType) {
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    $this->subscriptionService->handlePayPalSubscriptionActivated($body);
                    $handled = true;
                    break;
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                case 'BILLING.SUBSCRIPTION.EXPIRED':
                    $this->subscriptionService->handlePayPalSubscriptionCancelled($body);
                    $handled = true;
                    break;
                case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                    $this->subscriptionService->handlePayPalPaymentFailed($body);
                    $handled = true;
                    break;
                case 'PAYMENT.SALE.COMPLETED':
                    $this->subscriptionService->handlePayPalChargeSuccess($body);
                    $handled = true;
                    break;
                default:
                    Log::info('PayPal webhook: Unhandled event', $ctx);
            }
            if ($handled) {
                Log::info('PayPal webhook: handler completed', ['event_type' => $eventType]);
            }
            WebhookEvent::record('paypal', 'processed', 200, $eventType, $ctx['resource_id'] ?? null);

            return response('Webhook handled', 200);
        } catch (\Throwable $e) {
            Log::error('PayPal webhook: Error handling event', array_merge($ctx, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));
            WebhookEvent::record('paypal', 'failed', 500, $eventType ?? null, $ctx['resource_id'] ?? null, $e->getMessage());

            return response('Webhook processing failed', 500);
        }
    }
}
