# Subscription & Payment Notifications

## When notifications are sent

| Event | In-app | Email | Trigger |
|-------|--------|-------|---------|
| **Subscription Activated** | Yes | If enabled | Checkout completed (Stripe webhook + test mode) |
| **Subscription Renewed** | Yes | If enabled | Invoice paid (Stripe webhook, `billing_reason=subscription_cycle`) |
| **Subscription Cancelled** | Yes | If enabled | Subscription deleted or grace period ended |
| **Payment Failed** | Yes | Always | Invoice payment failed (Stripe webhook) |

## User preferences

Users can toggle email notifications in **Settings > Email Notifications**:
- `subscription_activated`
- `subscription_renewed`
- `subscription_cancelled`
- `payment_failed` (critical; email always sent regardless of preference)

## Implementation

- **In-app**: `NotificationService::create()` – always sent
- **Email**: Laravel Notifications – respects `MemoraEmailNotification` (default: on if no record)
- **payment_failed**: Email always sent (critical; user must act)
