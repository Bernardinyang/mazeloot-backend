<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProjectStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemoraProject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_uuid',
        'name',
        'description',
        'status',
        'color',
        'has_selections',
        'has_proofing',
        'has_collections',
        'parent_uuid',
        'preset_uuid',
        'watermark_uuid',
        'settings',
    ];

    protected $casts = [
        'status' => ProjectStatusEnum::class,
        'has_selections' => 'boolean',
        'has_proofing' => 'boolean',
        'has_collections' => 'boolean',
        'settings' => 'array',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\ProjectFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_uuid', 'uuid');
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(MemoraPreset::class, 'preset_uuid', 'uuid');
    }

    public function watermark(): BelongsTo
    {
        return $this->belongsTo(MemoraWatermark::class, 'watermark_uuid', 'uuid');
    }

    public function mediaSets(): HasMany
    {
        return $this->hasMany(MemoraMediaSet::class, 'project_uuid', 'uuid');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(MemoraSelection::class, 'project_uuid', 'uuid');
    }

    public function proofing(): HasMany
    {
        return $this->hasMany(MemoraProofing::class, 'project_uuid', 'uuid');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(MemoraCollection::class, 'project_uuid', 'uuid');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MemoraProject::class, 'parent_uuid', 'uuid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MemoraProject::class, 'parent_uuid', 'uuid');
    }
}
