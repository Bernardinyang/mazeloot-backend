<?php

namespace App\Http\Controllers\V1\Webhooks;

use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Services\Payment\Providers\PaystackProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(
        protected PaystackProvider $paystack,
        protected MemoraSubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->attributes->get('paystack_raw_payload');
        if ($payload === null || $payload === '') {
            $payload = $request->getContent();
        }
        if ($payload === false) {
            $payload = '';
        }
        $signature = $request->header('x-paystack-signature');

        Log::info('Paystack webhook: request received', [
            'payload_length' => strlen($payload),
            'has_signature' => ! empty($signature),
            'content_type' => $request->header('content-type'),
        ]);
        if ($payload === '') {
            Log::warning('Paystack webhook: empty body - ensure no middleware consumes request body before webhook');
        }

        if (! $signature) {
            Log::warning('Paystack webhook: Missing signature', ['has_payload' => ! empty($payload)]);

            return response('Missing signature', 400);
        }

        if (! $this->paystack->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook: Invalid signature', ['payload_length' => strlen($payload)]);

            return response('Invalid signature', 400);
        }

        $body = json_decode($payload, true);
        if (! is_array($body)) {
            Log::warning('Paystack webhook: Invalid payload (non-array JSON)', ['payload_preview' => substr($payload, 0, 200)]);

            return response('Invalid payload', 400);
        }

        $event = $body['event'] ?? $body['type'] ?? '';
        $event = str_replace('_', '.', (string) $event);
        $data = $body['data'] ?? [];
        $ctx = [
            'event' => $event,
            'reference' => $data['reference'] ?? null,
            'subscription_code' => isset($data['subscription']) ? (is_array($data['subscription']) ? ($data['subscription']['subscription_code'] ?? $data['subscription']['id'] ?? null) : null) : null,
            'data_keys' => array_keys($data),
        ];

        Log::info('Paystack webhook received', $ctx);

        try {
            $handled = false;
            switch ($event) {
                case 'charge.success':
                    $this->handleChargeSuccess($data);
                    $handled = true;
                    break;
                case 'subscription.create':
                    Log::info('Paystack webhook: dispatching subscription.create');
                    $this->handleSubscriptionCreate($data);
                    $handled = true;
                    break;
                case 'subscription.disable':
                    $this->handleSubscriptionDisable($data);
                    $handled = true;
                    break;
                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($data);
                    $handled = true;
                    break;
                case 'invoice.update':
                    $this->handleInvoiceUpdate($data);
                    $handled = true;
                    break;
                default:
                    Log::info('Paystack webhook: Unhandled event', $ctx);
            }
            if ($handled) {
                Log::info('Paystack webhook: handler completed', ['event' => $event]);
            }
        } catch (\Throwable $e) {
            Log::error('Paystack webhook: Error handling event', array_merge($ctx, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));

            return response('Webhook processing failed', 500);
        }

        return response('Webhook handled', 200);
    }

    protected function handleChargeSuccess(array $data): void
    {
        $subscription = $data['subscription'] ?? null;
        if (is_array($subscription)) {
            $subscriptionCode = $subscription['subscription_code'] ?? $subscription['id'] ?? null;
            if ($subscriptionCode) {
                $this->subscriptionService->handlePaystackChargeSuccess($data);

                return;
            }
        }
        $metadata = $data['metadata'] ?? [];
        if (is_array($metadata) && ! empty($metadata['user_uuid']) && ! empty($data['reference'])) {
            $this->subscriptionService->handlePaystackInitialChargeSuccess($data);
        }
    }

    protected function handleSubscriptionCreate(array $data): void
    {
        $this->subscriptionService->handlePaystackSubscriptionCreate($data);
    }

    protected function handleSubscriptionDisable(array $data): void
    {
        $this->subscriptionService->handlePaystackSubscriptionDisable($data);
    }

    protected function handleInvoicePaymentFailed(array $data): void
    {
        $this->subscriptionService->handlePaystackInvoicePaymentFailed($data);
    }

    protected function handleInvoiceUpdate(array $data): void
    {
        $status = $data['status'] ?? null;
        if ($status === 'failed' || ($data['paid'] ?? false) === false && in_array($status, ['failed', 'attention'], true)) {
            $this->subscriptionService->handlePaystackInvoicePaymentFailed($data);
        }
    }
}
