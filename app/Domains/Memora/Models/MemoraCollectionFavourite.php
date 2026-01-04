<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemoraCollectionFavourite extends Model
{
    protected $table = 'memora_collection_favourites';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'collection_uuid',
        'media_uuid',
        'email',
        'user_uuid',
        'note',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
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

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoraCollection::class, 'collection_uuid', 'uuid');
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

