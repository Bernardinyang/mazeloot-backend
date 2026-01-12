<?php

namespace App\Domains\Memora\Models;

use Database\Factories\MediaSetFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemoraMediaSet extends Model
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
        'selection_uuid',
        'proof_uuid',
        'collection_uuid',
        'raw_files_uuid',
        'name',
        'description',
        'order',
        'selection_limit',
    ];

    protected $casts = [
        'selection_limit' => 'integer',
        'order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        // Update storage cache when media set is soft deleted
        static::deleted(function ($model) {
            if ($model->user_uuid) {
                try {
                    $storageService = app(\App\Services\Storage\UserStorageService::class);
                    $storageService->calculateAndCacheStorage($model->user_uuid);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to update storage cache after media set deletion', [
                        'media_set_uuid' => $model->uuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    protected static function newFactory(): Factory|MediaSetFactory
    {
        return MediaSetFactory::new();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MemoraProject::class, 'project_uuid', 'uuid');
    }

    public function selection(): BelongsTo
    {
        return $this->belongsTo(MemoraSelection::class, 'selection_uuid', 'uuid');
    }

    public function proofing(): BelongsTo
    {
        return $this->belongsTo(MemoraProofing::class, 'proof_uuid', 'uuid');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoraCollection::class, 'collection_uuid', 'uuid');
    }

    public function rawFiles(): BelongsTo
    {
        return $this->belongsTo(MemoraRawFiles::class, 'raw_files_uuid', 'uuid');
    }

    public function media(): HasMany
    {
        return $this->hasMany(MemoraMedia::class, 'media_set_uuid', 'uuid');
    }
}
