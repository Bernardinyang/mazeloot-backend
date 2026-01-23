<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EarlyAccessUser extends Model
{
    use HasFactory;

    protected $table = 'early_access_users';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_uuid',
        'discount_percentage',
        'discount_rules',
        'feature_flags',
        'storage_multiplier',
        'priority_support',
        'exclusive_badge',
        'trial_extension_days',
        'custom_branding_enabled',
        'release_version',
        'granted_at',
        'expires_at',
        'is_active',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_rules' => 'array',
            'feature_flags' => 'array',
            'storage_multiplier' => 'decimal:2',
            'priority_support' => 'boolean',
            'exclusive_badge' => 'boolean',
            'trial_extension_days' => 'integer',
            'custom_branding_enabled' => 'boolean',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->granted_at)) {
                $model->granted_at = now();
            }
        });
    }

    /**
     * Get the user that owns the early access.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Check if early access is active.
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has a specific feature flag.
     */
    public function hasFeature(string $feature): bool
    {
        $flags = $this->feature_flags ?? [];
        return in_array($feature, $flags);
    }

    /**
     * Get discount percentage for a specific product.
     */
    public function getDiscountForProduct(string $productId): int
    {
        // Check product-specific rules first
        if ($this->discount_rules && isset($this->discount_rules[$productId])) {
            return (int) $this->discount_rules[$productId];
        }

        // Return default discount percentage
        return $this->discount_percentage ?? 0;
    }

    /**
     * Get storage multiplier.
     */
    public function getStorageMultiplier(): float
    {
        return (float) ($this->storage_multiplier ?? 1.0);
    }

    /**
     * Check if user has priority support.
     */
    public function hasPrioritySupport(): bool
    {
        return $this->priority_support ?? false;
    }

    /**
     * Check if user has exclusive badge.
     */
    public function hasExclusiveBadge(): bool
    {
        return $this->exclusive_badge ?? true;
    }
}
