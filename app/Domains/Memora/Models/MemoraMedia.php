<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\MediaTypeEnum;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemoraMedia extends Model
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
        'user_uuid',
        'media_set_uuid',
        'is_selected',
        'selected_at',
        'revision_number',
        'is_completed',
        'completed_at',
        'original_media_uuid',
        'user_file_uuid',
        'url',
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

    protected static function newFactory(): Factory|MediaFactory
    {
        return MediaFactory::new();
    }

    public function mediaSet(): BelongsTo
    {
        return $this->belongsTo(MemoraMediaSet::class, 'media_set_uuid', 'uuid');
    }

    public function feedback()
    {
        return $this->hasMany(MemoraMediaFeedback::class, 'media_uuid', 'uuid');
    }

    /**
     * Get the file that this media is associated with
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserFile::class, 'user_file_uuid', 'uuid');
    }
}
