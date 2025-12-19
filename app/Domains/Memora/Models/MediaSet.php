<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaSet extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'memora_media_sets';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'set_id');
    }

    protected static function newFactory()
    {
        return \Database\Factories\MediaSetFactory::new();
    }
}