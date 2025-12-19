<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProjectStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoraCollection extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'color',
    ];

    protected $casts = [
        'status' => ProjectStatusEnum::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(MemoraProject::class, 'project_id');
    }
}
