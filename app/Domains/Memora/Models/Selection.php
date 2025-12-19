<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\SelectionStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Selection extends Model
{
    use HasUuids;

    protected $table = 'memora_selections';

    protected $fillable = [
        'project_id',
        'name',
        'status',
        'color',
        'selection_completed_at',
        'auto_delete_date',
    ];

    protected $casts = [
        'status' => SelectionStatusEnum::class,
        'selection_completed_at' => 'datetime',
        'auto_delete_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}