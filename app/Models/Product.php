<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

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

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'is_active',
        'order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Get the id attribute (returns uuid since that's the primary key).
     *
     * @return string|null
     */
    public function getIdAttribute()
    {
        return $this->uuid;
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
        });
    }

    /**
     * Get the user product selections for this product.
     */
    public function userProductSelections(): HasMany
    {
        return $this->hasMany(UserProductSelection::class, 'product_uuid', 'uuid');
    }

    /**
     * Get the user onboarding tokens for this product.
     */
    public function userOnboardingTokens(): HasMany
    {
        return $this->hasMany(UserOnboardingToken::class, 'product_uuid', 'uuid');
    }

    /**
     * Get the user onboarding statuses for this product.
     */
    public function userOnboardingStatuses(): HasMany
    {
        return $this->hasMany(UserOnboardingStatus::class, 'product_uuid', 'uuid');
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order products by order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get onboarding steps from metadata.
     */
    public function getOnboardingStepsAttribute(): array
    {
        return $this->metadata['onboarding_steps'] ?? [];
    }
}
