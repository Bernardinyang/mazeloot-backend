# Paystack Webhooks

## Webhook URL

Configure this URL in your Paystack Dashboard (Settings → API Keys & Webhooks) for both **Test** and **Live**:

```
https://<your-api-domain>/api/v1/webhooks/paystack
```

Example: `https://api.example.com/api/v1/webhooks/paystack`

- Use your actual API base URL (no trailing slash).
- The same endpoint is used for test and live; Paystack identifies the environment by the secret key used to sign the request.

## Signature verification

Webhooks are verified using your Paystack **secret key** (test or live). The `x-paystack-signature` header contains an HMAC SHA512 of the raw body. Ensure:

- **Test mode**: Set `PAYSTACK_TEST_SECRET_KEY` and use test keys in the Paystack dashboard when adding the webhook URL.
- **Live mode**: Set `PAYSTACK_LIVE_SECRET_KEY` and use live keys when adding the webhook URL.

No separate webhook secret is used; the same secret key that signs API requests is used to verify webhook payloads.

## Events handled

| Event | Action |
|-------|--------|
| `charge.success` | Recurring renewal: update subscription period, send renewal notification |
| `subscription.create` | Create Memora subscription, set user tier, send activated notification |
| `subscription.disable` | Mark subscription canceled, downgrade user, send cancelled notification |
| `invoice.payment_failed` | Send payment-failed notification to user |
| `invoice.update` | If status failed/attention, same as invoice.payment_failed |

## Local testing with ngrok

Paystack cannot reach `localhost`. Use [ngrok](https://ngrok.com) to expose your local API so webhooks can be delivered.

### 1. Install ngrok

- **macOS (Homebrew):** `brew install ngrok`
- **Or:** [Download](https://ngrok.com/download) and add to PATH

### 2. Start your Laravel server

```bash
php artisan serve
```

Runs on `http://127.0.0.1:8000` by default.

### 3. Start ngrok tunnel

In another terminal, from the backend directory:

```bash
bun run webhook:tunnel
```

Or directly:

```bash
ngrok http 8000
```

ngrok will show a public URL, e.g. `https://abc123.ngrok-free.app`.

### 4. Set webhook URL in Paystack

1. Open [Paystack Dashboard](https://dashboard.paystack.com) → **Settings** → **API Keys & Webhooks**.
2. Under **Webhook URL**, set:
   ```
   https://<your-ngrok-host>/api/v1/webhooks/paystack
   ```
   Example: `https://abc123.ngrok-free.app/api/v1/webhooks/paystack`
3. Use **Test** mode and test keys so no real charges occur.

### 5. Test

Trigger a test subscription checkout; Paystack will POST events to your ngrok URL, which forwards to `localhost:8000`. The free ngrok URL changes each time you restart ngrok unless you use a reserved domain.
