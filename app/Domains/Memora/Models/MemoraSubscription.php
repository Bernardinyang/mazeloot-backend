<?php

namespace App\Domains\Memora\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoraSubscription extends Model
{
    use HasUuids;

    protected $table = 'memora_subscriptions';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'payment_provider',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_price_id',
        'tier',
        'billing_cycle',
        'status',
        'amount',
        'currency',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'trial_ends_at',
        'metadata',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled' || $this->canceled_at !== null;
    }

    public function onGracePeriod(): bool
    {
        return $this->canceled_at !== null && $this->current_period_end?->isFuture();
    }

    /**
     * Return the effective period end for display (renewal date).
     * If stored current_period_end is missing or not after period_start, derive from billing cycle.
     */
    public function getEffectivePeriodEnd(): ?\Illuminate\Support\Carbon
    {
        $start = $this->current_period_start;
        $end = $this->current_period_end;
        if ($start && $end && $end->gt($start)) {
            return $end;
        }
        if (! $start) {
            return $end;
        }

        return $this->billing_cycle === 'annual'
            ? $start->copy()->addYear()
            : $start->copy()->addMonth();
    }
}
