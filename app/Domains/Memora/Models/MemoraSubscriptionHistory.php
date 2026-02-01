<?php

namespace App\Domains\Memora\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoraSubscriptionHistory extends Model
{
    use HasUuids;

    protected $table = 'memora_subscription_history';

    protected $fillable = [
        'user_uuid',
        'event_type',
        'from_tier',
        'to_tier',
        'billing_cycle',
        'amount_cents',
        'currency',
        'payment_provider',
        'payment_reference',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @param  string  $userUuid
     * @param  string  $eventType
     * @param  string|null  $fromTier
     * @param  string|null  $toTier
     * @param  string|null  $billingCycle
     * @param  int|null  $amountCents  Amount in smallest unit (USD cents, NGN kobo, etc.)
     * @param  string|null  $paymentProvider
     * @param  string|null  $paymentReference
     * @param  array|null  $metadata
     * @param  string|null  $notes
     * @param  string|null  $currency  Currency of amount_cents (e.g. 'usd', 'ngn'). Paystack amounts are in kobo.
     */
    public static function record(
        string $userUuid,
        string $eventType,
        ?string $fromTier = null,
        ?string $toTier = null,
        ?string $billingCycle = null,
        ?int $amountCents = null,
        ?string $paymentProvider = null,
        ?string $paymentReference = null,
        ?array $metadata = null,
        ?string $notes = null,
        ?string $currency = null
    ): self {
        $currency = $currency ? strtoupper($currency) : 'USD';
        return self::create([
            'user_uuid' => $userUuid,
            'event_type' => $eventType,
            'from_tier' => $fromTier,
            'to_tier' => $toTier,
            'billing_cycle' => $billingCycle,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'payment_provider' => $paymentProvider,
            'payment_reference' => $paymentReference,
            'metadata' => $metadata,
            'notes' => $notes,
        ]);
    }
}
