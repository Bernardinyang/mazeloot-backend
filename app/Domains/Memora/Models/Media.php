<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\MediaTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'memora_media';

    protected $fillable = [
        'user_uuid',
        'media_set_uuid',
        'is_selected',
        'selected_at',
        'revision_number',
        'is_completed',
        'completed_at',
        'original_media_uuid',
        'url',
        'thumbnail_url',
        'low_res_copy_url',
        'type',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
        'order',
    ];

    protected $casts = [
        'type' => MediaTypeEnum::class,
        'is_selected' => 'boolean',
        'selected_at' => 'datetime',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function mediaSet(): BelongsTo
    {
        return $this->belongsTo(MediaSet::class, 'media_set_uuid', 'uuid');
    }

    public function feedback()
    {
        return $this->hasMany(MediaFeedback::class, 'media_id');
    }

    protected static function newFactory()
    {
        return \Database\Factories\MediaFactory::new();
    }
}