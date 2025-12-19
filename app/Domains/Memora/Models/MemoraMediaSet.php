<?php

namespace App\Domains\Memora\Models;

use Database\Factories\MediaSetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemoraMediaSet extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'order',
    ];

    protected static function newFactory(): Factory|MediaSetFactory
    {
        return MediaSetFactory::new();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MemoraProject::class, 'project_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(MemoraMedia::class, 'set_id');
    }
}
