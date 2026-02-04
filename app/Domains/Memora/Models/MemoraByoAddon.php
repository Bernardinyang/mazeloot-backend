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
        'cost_monthly_cents',
        'cost_annual_cents',
        'storage_bytes',
        'selection_limit_granted',
        'proofing_limit_granted',
        'collection_limit_granted',
        'project_limit_granted',
        'raw_file_limit_granted',
        'max_revisions_granted',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'storage_bytes' => 'integer',
        'selection_limit_granted' => 'integer',
        'proofing_limit_granted' => 'integer',
        'collection_limit_granted' => 'integer',
        'project_limit_granted' => 'integer',
        'raw_file_limit_granted' => 'integer',
        'max_revisions_granted' => 'integer',
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
