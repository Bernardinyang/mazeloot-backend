<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\RawFileStatusEnum;
use Database\Factories\MemoraRawFileFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemoraRawFile extends Model
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
        'name',
        'description',
        'status',
        'color',
        'cover_photo_url',
        'cover_focal_point',
        'password',
        'allowed_emails',
        'raw_file_completed_at',
        'completed_by_email',
        'raw_file_limit',
        'reset_raw_file_limit_at',
        'auto_delete_date',
        'auto_delete_enabled',
        'auto_delete_days',
        'display_settings',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'status' => RawFileStatusEnum::class,
        'raw_file_completed_at' => 'datetime',
        'reset_raw_file_limit_at' => 'datetime',
        'auto_delete_date' => 'date',
        'auto_delete_enabled' => 'boolean',
        'auto_delete_days' => 'integer',
        'raw_file_limit' => 'integer',
        'cover_focal_point' => 'array',
        'display_settings' => 'array',
        'allowed_emails' => 'array',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(MemoraProject::class, 'project_uuid', 'uuid');
    }

    public function mediaSets(): HasMany
    {
        return $this->hasMany(MemoraMediaSet::class, 'raw_file_uuid', 'uuid');
    }

    /**
     * Get the users who have starred this raw file.
     */
    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_starred_raw_files',
            'raw_file_uuid',
            'user_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return MemoraRawFileFactory::new();
    }
}
