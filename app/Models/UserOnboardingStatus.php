<?php

namespace App\Models;

use App\Support\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboardingStatus extends Model
{
    use HasCompositePrimaryKey;

    /**
     * Indicates if the model uses auto-incrementing IDs.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_onboarding_status';

    /**
     * Get the composite key fields.
     *
     * @return array<string>
     */
    protected function getCompositeKeyFields(): array
    {
        return ['user_uuid', 'product_uuid'];
    }

    protected $fillable = [
        'user_uuid',
        'product_uuid',
        'completed_at',
        'completed_steps',
        'onboarding_data',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'completed_steps' => 'array',
        'onboarding_data' => 'array',
    ];

    /**
     * Get the user that owns the onboarding status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the product for this onboarding status.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    /**
     * Check if onboarding is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Mark a step as complete.
     */
    public function markStepComplete(string $step): void
    {
        $steps = $this->completed_steps ?? [];
        if (! in_array($step, $steps)) {
            $steps[] = $step;
            $this->update(['completed_steps' => $steps]);
        }
    }

    /**
     * Mark onboarding as complete.
     */
    public function markCompleted(): void
    {
        $this->update(['completed_at' => now()]);
    }

    /**
     * Get onboarding data.
     */
    public function getOnboardingDataAttribute($value): array
    {
        return $this->attributes['onboarding_data'] ? json_decode($this->attributes['onboarding_data'], true) : [];
    }
}
