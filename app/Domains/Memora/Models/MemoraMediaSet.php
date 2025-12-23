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
        'name',
        'description',
        'order',
    ];

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

    public function media(): HasMany
    {
        return $this->hasMany(MemoraMedia::class, 'media_set_uuid', 'uuid');
    }
}
