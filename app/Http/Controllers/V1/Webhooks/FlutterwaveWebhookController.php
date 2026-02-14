<?php

namespace App\Http\Controllers\V1\Webhooks;

use App\Domains\Memora\Services\MemoraSubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Payment\Providers\FlutterwaveProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FlutterwaveWebhookController extends Controller
{
    public function __construct(
        protected FlutterwaveProvider $flutterwave,
        protected MemoraSubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->attributes->get('flutterwave_raw_payload');
        if ($payload === null || $payload === '') {
            $payload = $request->getContent();
        }
        if ($payload === false) {
            $payload = '';
        }
        $signature = $request->header('flutterwave-signature');
        $body = json_decode($payload, true);
        $body = is_array($body) ? $body : [];
        if ($signature === null || $signature === '') {
            $data = $body['data'] ?? $body;
            $signature = (string) ($body['verif_hash'] ?? $data['verif_hash'] ?? '');
        }

        Log::info('Flutterwave webhook: request received', [
            'payload_length' => strlen($payload),
            'has_signature' => ! empty($signature),
            'content_type' => $request->header('content-type'),
        ]);
        if ($payload === '') {
            Log::warning('Flutterwave webhook: empty body - ensure no middleware consumes request body before webhook');
        }

        $isTestMode = config('payment.providers.flutterwave.test_mode', false);
        if (! $signature) {
            if ($isTestMode) {
                Log::warning('Flutterwave webhook: No signature in test mode - accepting payload (use signature in production)');
            } else {
                Log::warning('Flutterwave webhook: Missing signature (header and verif_hash)', ['has_payload' => ! empty($payload)]);
                WebhookEvent::record('flutterwave', 'failed', 400, null, null, 'Missing signature');

                return response('Missing signature', 400);
            }
        } elseif (! $this->flutterwave->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Flutterwave webhook: Invalid signature', ['payload_length' => strlen($payload)]);
            WebhookEvent::record('flutterwave', 'failed', 400, null, null, 'Invalid signature');

            return response('Invalid signature', 400);
        }

        if (empty($body)) {
            Log::warning('Flutterwave webhook: Invalid payload (empty or non-array JSON)', ['payload_preview' => substr($payload, 0, 200)]);
            WebhookEvent::record('flutterwave', 'failed', 400, null, null, 'Invalid payload');

            return response('Invalid payload', 400);
        }

        $event = $body['type'] ?? $body['event'] ?? '';
        $event = str_replace('_', '.', (string) $event);
        $data = $body['data'] ?? [];
        $ctx = [
            'event' => $event,
            'tx_ref' => $data['tx_ref'] ?? $data['reference'] ?? null,
            'id' => $data['id'] ?? null,
            'data_keys' => array_keys($data),
        ];

        Log::info('Flutterwave webhook received', $ctx);

        try {
            $handled = false;
            switch ($event) {
                case 'charge.completed':
                    $this->handleChargeCompleted($data);
                    $handled = true;
                    break;
                case 'charge.failed':
                    Log::info('Flutterwave webhook: charge.failed', $ctx);
                    $handled = true;
                    break;
                default:
                    Log::info('Flutterwave webhook: Unhandled event', $ctx);
            }
            if ($handled) {
                Log::info('Flutterwave webhook: handler completed', ['event' => $event]);
            }
            $eventId = $ctx['tx_ref'] ?? $ctx['id'] ?? null;
            WebhookEvent::record('flutterwave', 'processed', 200, $event, is_string($eventId) ? $eventId : (is_scalar($eventId) ? (string) $eventId : null));

            return response('Webhook handled', 200);
        } catch (\Throwable $e) {
            Log::error('Flutterwave webhook: Error handling event', array_merge($ctx, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));
            WebhookEvent::record('flutterwave', 'failed', 500, $event ?? null, $ctx['tx_ref'] ?? null, $e->getMessage());

            return response('Webhook processing failed', 500);
        }
    }

    protected function handleChargeCompleted(array $data): void
    {
        $status = strtolower((string) ($data['status'] ?? ''));
        if (! in_array($status, ['succeeded', 'successful'], true)) {
            Log::info('Flutterwave webhook: charge.completed with non-success status', ['status' => $data['status'] ?? null]);

            return;
        }

        $txRef = $data['tx_ref'] ?? $data['reference'] ?? null;
        if (! $txRef) {
            Log::warning('Flutterwave webhook: charge.completed missing tx_ref/reference');

            return;
        }

        $this->subscriptionService->handleFlutterwaveInitialChargeSuccess($data);
    }
}
