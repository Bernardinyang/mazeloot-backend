<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\ProjectStatusEnum;
use Database\Factories\MemoraCollectionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'name',
        'description',
        'status',
        'color',
    ];

    protected $casts = [
        'status' => ProjectStatusEnum::class,
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

    /**
     * Get the cover layout relationship (optional - if cover_layout_uuid is stored)
     */
    public function coverLayout(): BelongsTo
    {
        return $this->belongsTo(MemoraCoverLayout::class, 'cover_layout_uuid', 'uuid');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return MemoraCollectionFactory::new();
    }
}
