<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemoraGuestCollectionToken extends Model
{
    protected $table = 'memora_guest_collection_tokens';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'collection_uuid',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->token)) {
                $model->token = Str::random(64);
            }
        });
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoraCollection::class, 'collection_uuid', 'uuid');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
