<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MemoraByoConfig extends Model
{
    protected $table = 'memora_byo_config';

    protected $fillable = [
        'base_price_monthly_cents',
        'base_price_annual_cents',
        'base_cost_monthly_cents',
        'base_cost_annual_cents',
        'base_storage_bytes',
        'base_project_limit',
        'base_selection_limit',
        'base_proofing_limit',
        'base_collection_limit',
        'base_raw_file_limit',
        'base_max_revisions',
        'annual_discount_months',
    ];

    public static function getConfig(): ?self
    {
        return Cache::remember('memora_byo_config', 3600, fn () => self::first());
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('memora_byo_config'));
    }
}
