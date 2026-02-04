<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MemoraPricingTier extends Model
{
    protected $table = 'memora_pricing_tiers';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_monthly_cents',
        'price_annual_cents',
        'storage_bytes',
        'project_limit',
        'collection_limit',
        'selection_limit',
        'proofing_limit',
        'max_revisions',
        'watermark_limit',
        'preset_limit',
        'team_seats',
        'raw_file_limit',
        'features',
        'features_display',
        'set_limit_per_phase',
        'capabilities',
        'sort_order',
        'is_popular',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'features_display' => 'array',
        'capabilities' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public static function getBySlug(string $slug): ?self
    {
        return Cache::remember("memora_pricing_tier_{$slug}", 3600, fn () => self::where('slug', $slug)->first());
    }

    public static function getAllActive(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('memora_pricing_tiers_all', 3600, fn () => self::active()->ordered()->get());
    }

    protected static function booted(): void
    {
        static::saved(function (self $model) {
            Cache::forget('memora_pricing_tiers_all');
            Cache::forget("memora_pricing_tier_{$model->slug}");
        });
    }
}
