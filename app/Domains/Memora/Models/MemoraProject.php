<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProjectStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemoraProject extends Model
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
        'name',
        'description',
        'status',
        'color',
        'has_selections',
        'has_proofing',
        'has_collections',
        'preset_uuid',
        'watermark_uuid',
        'settings',
    ];

    protected $casts = [
        'status' => ProjectStatusEnum::class,
        'has_selections' => 'boolean',
        'has_proofing' => 'boolean',
        'has_collections' => 'boolean',
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

    protected static function newFactory()
    {
        return \Database\Factories\ProjectFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_uuid', 'uuid');
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(MemoraPreset::class, 'preset_uuid', 'uuid');
    }

    public function watermark(): BelongsTo
    {
        return $this->belongsTo(MemoraWatermark::class, 'watermark_uuid', 'uuid');
    }

    public function mediaSets(): HasMany
    {
        return $this->hasMany(MemoraMediaSet::class, 'project_uuid', 'uuid');
    }

    public function selection(): HasOne
    {
        return $this->hasOne(MemoraSelection::class, 'project_uuid', 'uuid');
    }

    public function proofing(): HasOne
    {
        return $this->hasOne(MemoraProofing::class, 'project_uuid', 'uuid');
    }

    public function collection(): HasOne
    {
        return $this->hasOne(MemoraCollection::class, 'project_uuid', 'uuid');
    }

    /**
     * Get the users who have starred this project.
     */
    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_starred_projects',
            'project_uuid',
            'user_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }
}
