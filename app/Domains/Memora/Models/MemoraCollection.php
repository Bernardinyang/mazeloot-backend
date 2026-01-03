<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProjectStatusEnum;
use Database\Factories\MemoraCollectionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemoraCollection extends Model
{
    use HasFactory, SoftDeletes;

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
        'project_uuid',
        'preset_uuid',
        'watermark_uuid',
        'name',
        'description',
        'status',
        'color',
        'settings',
    ];

    protected $casts = [
        'status' => ProjectStatusEnum::class,
        'settings' => 'array',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_uuid', 'uuid');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MemoraProject::class, 'project_uuid', 'uuid');
    }

    /**
     * Get the preset relationship
     */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(MemoraPreset::class, 'preset_uuid', 'uuid');
    }

    /**
     * Get the watermark relationship
     */
    public function watermark(): BelongsTo
    {
        return $this->belongsTo(MemoraWatermark::class, 'watermark_uuid', 'uuid');
    }

    /**
     * Get the users who starred this collection.
     */
    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_starred_collections',
            'collection_uuid',
            'user_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Get the media sets for this collection.
     */
    public function mediaSets(): HasMany
    {
        return $this->hasMany(MemoraMediaSet::class, 'collection_uuid', 'uuid');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return MemoraCollectionFactory::new();
    }
}
