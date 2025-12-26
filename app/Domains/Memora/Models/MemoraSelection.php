<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\SelectionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemoraSelection extends Model
{
    use SoftDeletes;
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
        'selection_completed_at',
        'completed_by_email',
        'selection_limit',
        'reset_selection_limit_at',
        'auto_delete_date',
        'auto_delete_enabled',
        'auto_delete_days',
        'display_settings',
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
        'status' => SelectionStatusEnum::class,
        'selection_completed_at' => 'datetime',
        'reset_selection_limit_at' => 'datetime',
        'auto_delete_date' => 'date',
        'auto_delete_enabled' => 'boolean',
        'auto_delete_days' => 'integer',
        'selection_limit' => 'integer',
        'cover_focal_point' => 'array',
        'display_settings' => 'array',
        'allowed_emails' => 'array',
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
        return $this->hasMany(MemoraMediaSet::class, 'selection_uuid', 'uuid');
    }

    /**
     * Get the users who have starred this selection.
     */
    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_starred_selections',
            'selection_uuid',
            'user_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MemoraSelectionFactory::new();
    }
}
