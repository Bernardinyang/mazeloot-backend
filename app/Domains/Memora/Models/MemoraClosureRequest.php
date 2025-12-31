<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemoraClosureRequest extends Model
{
    use SoftDeletes;
    
    protected $table = 'memora_closure_requests';
    
    protected $primaryKey = 'uuid';
    
    protected $keyType = 'string';
    
    public $incrementing = false;
    
    protected $fillable = [
        'proofing_uuid',
        'media_uuid',
        'user_uuid',
        'token',
        'todos',
        'status',
        'approved_at',
        'rejected_at',
        'approved_by_email',
        'rejection_reason',
        'rejected_by_email',
    ];

    protected $casts = [
        'todos' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->token)) {
                $model->token = (string) Str::random(64);
            }
        });
    }

    public function proofing(): BelongsTo
    {
        return $this->belongsTo(MemoraProofing::class, 'proofing_uuid', 'uuid');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MemoraMedia::class, 'media_uuid', 'uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_uuid', 'uuid');
    }
}

