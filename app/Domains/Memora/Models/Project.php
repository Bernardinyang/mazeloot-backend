<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'memora_projects';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'color',
        'has_selections',
        'has_proofing',
        'has_collections',
        'parent_id',
        'preset_id',
        'watermark_id',
        'settings',
    ];

    protected $casts = [
        'has_selections' => 'boolean',
        'has_proofing' => 'boolean',
        'has_collections' => 'boolean',
        'settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function mediaSets(): HasMany
    {
        return $this->hasMany(MediaSet::class, 'project_id');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(Selection::class, 'project_id');
    }

    public function proofing(): HasMany
    {
        return $this->hasMany(Proofing::class, 'project_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'project_id');
    }

    protected static function newFactory()
    {
        return \Database\Factories\ProjectFactory::new();
    }
}
