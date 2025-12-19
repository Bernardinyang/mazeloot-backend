<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProofingStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoraProofing extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'project_id',
        'name',
        'status',
        'color',
        'max_revisions',
        'current_revision',
        'completed_at',
    ];

    protected $casts = [
        'status' => ProofingStatusEnum::class,
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(MemoraProject::class, 'project_id');
    }
}
