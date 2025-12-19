<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\MediaFeedbackTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoraMediaFeedback extends Model
{
    use HasUuids;

    protected $fillable = [
        'media_id',
        'type',
        'content',
        'created_by',
    ];

    protected $casts = [
        'type' => MediaFeedbackTypeEnum::class,
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(MemoraMedia::class, 'media_id');
    }
}
