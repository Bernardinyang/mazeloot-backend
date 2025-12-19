<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\SelectionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemoraSelection extends Model
{
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
        'project_uuid',
        'name',
        'status',
        'color',
        'selection_completed_at',
        'auto_delete_date',
    ];

    protected $casts = [
        'status' => SelectionStatusEnum::class,
        'selection_completed_at' => 'datetime',
        'auto_delete_date' => 'date',
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
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MemoraSelectionFactory::new();
    }
}
