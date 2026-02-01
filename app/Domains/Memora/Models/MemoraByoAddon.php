<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;

class MemoraByoAddon extends Model
{
    protected $table = 'memora_byo_addons';

    protected $fillable = [
        'slug',
        'label',
        'type',
        'price_monthly_cents',
        'price_annual_cents',
        'storage_bytes',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'storage_bytes' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
