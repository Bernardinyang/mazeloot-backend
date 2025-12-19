<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collection extends Model
{
    use HasUuids;

    protected $table = 'memora_collections';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'color',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}