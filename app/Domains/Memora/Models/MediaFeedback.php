<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFeedback extends Model
{
    use HasUuids;

    protected $table = 'memora_media_feedback';

    protected $fillable = [
        'media_id',
        'type',
        'content',
        'created_by',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}