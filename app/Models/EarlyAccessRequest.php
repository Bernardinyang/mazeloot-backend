<?php

namespace App\Models;

use App\Enums\EarlyAccessRequestStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EarlyAccessRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'early_access_requests';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_uuid',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => EarlyAccessRequestStatusEnum::class,
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'uuid');
    }

    public function isPending(): bool
    {
        return $this->status === EarlyAccessRequestStatusEnum::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === EarlyAccessRequestStatusEnum::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === EarlyAccessRequestStatusEnum::REJECTED;
    }
}
