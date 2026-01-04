<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoraCollectionDownload extends Model
{
    protected $table = 'memora_collection_downloads';

    protected $fillable = [
        'collection_uuid',
        'media_uuid',
        'email',
        'user_uuid',
        'download_type',
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

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoraCollection::class, 'collection_uuid', 'uuid');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MemoraMedia::class, 'media_uuid', 'uuid');
    }
}

