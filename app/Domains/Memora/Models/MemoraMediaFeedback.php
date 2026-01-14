<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\MediaFeedbackTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MemoraMediaFeedback extends Model
{
    use HasFactory;

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
        'parent_uuid',
        'timestamp',
        'mentions',
        'type',
        'content',
        'created_by',
    ];

    protected $casts = [
        'type' => MediaFeedbackTypeEnum::class,
        'timestamp' => 'decimal:2',
        'created_by' => 'array', // Cast JSON to array
        'mentions' => 'array', // Cast JSON to array
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
     * Get the parent comment (for replies)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MemoraMediaFeedback::class, 'parent_uuid', 'uuid');
    }

    /**
     * Get all replies to this comment
     */
    public function replies(): HasMany
    {
        return $this->hasMany(MemoraMediaFeedback::class, 'parent_uuid', 'uuid')->orderBy('created_at', 'asc');
    }

    /**
     * Recursively load all nested replies (unlimited depth)
     */
    public function loadNestedReplies(): void
    {
        if (! $this->relationLoaded('replies')) {
            $this->load('replies');
        }

        foreach ($this->replies as $reply) {
            $reply->loadNestedReplies();
        }
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MemoraMediaFeedbackFactory::new();
    }
}
