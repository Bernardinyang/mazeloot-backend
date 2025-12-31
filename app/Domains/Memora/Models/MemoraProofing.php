<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProofingStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemoraProofing extends Model
{
    use SoftDeletes;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'memora_proofing';
    
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
        'max_revisions',
        'current_revision',
        'completed_at',
        'cover_photo_url',
        'cover_focal_point',
        'allowed_emails',
        'primary_email',
        'password',
    ];

    protected $casts = [
        'status' => ProofingStatusEnum::class,
        'completed_at' => 'datetime',
        'cover_focal_point' => 'array',
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
        return $this->hasMany(MemoraMediaSet::class, 'proof_uuid', 'uuid');
    }

    /**
     * Get the users who have starred this proofing.
     */
    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_starred_proofing',
            'proofing_uuid',
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
        return \Database\Factories\MemoraProofingFactory::new();
    }
}
