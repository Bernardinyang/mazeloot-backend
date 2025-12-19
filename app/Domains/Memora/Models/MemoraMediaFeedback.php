<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\MediaFeedbackTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemoraMediaFeedback extends Model
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';
    
    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'media_uuid',
        'type',
        'content',
        'created_by',
    ];

    protected $casts = [
        'type' => MediaFeedbackTypeEnum::class,
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MemoraMedia::class, 'media_uuid', 'uuid');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MemoraMediaFeedbackFactory::new();
    }
}
