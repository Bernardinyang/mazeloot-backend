<?php

namespace App\Models;

use App\Domains\Memora\Enums\MediaTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UserFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_files';

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
        'url',
        'path',
        'type',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
        'metadata',
    ];

    protected $casts = [
        'type' => MediaTypeEnum::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
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

        static::deleted(function ($model) {
            // Decrement storage when file is deleted (soft or hard delete)
            if ($model->user_uuid) {
                $storageService = app(\App\Services\Storage\UserStorageService::class);
                $totalSize = $storageService->calculateFileSizeFromMetadata((object) [
                    'metadata' => $model->metadata,
                    'size' => $model->size,
                ]);

                if ($totalSize > 0) {
                    $storageService->decrementStorage($model->user_uuid, $totalSize);
                }
            }
        });
    }

    /**
     * Get the user that owns the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get all media that use this user file
     */
    public function media(): HasMany
    {
        return $this->hasMany(\App\Domains\Memora\Models\MemoraMedia::class, 'user_file_uuid', 'uuid');
    }
}
