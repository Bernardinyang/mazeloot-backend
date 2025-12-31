<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\BorderStyleEnum;
use App\Domains\Memora\Enums\FontStyleEnum;
use App\Domains\Memora\Enums\TextTransformEnum;
use App\Domains\Memora\Enums\WatermarkPositionEnum;
use App\Domains\Memora\Enums\WatermarkTypeEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MemoraWatermark extends Model
{
    use HasFactory;

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
        'type',
        'image_url',
        'text',
        'font_family',
        'font_style',
        'font_color',
        'background_color',
        'line_height',
        'letter_spacing',
        'padding',
        'text_transform',
        'border_radius',
        'border_width',
        'border_color',
        'border_style',
        'scale',
        'opacity',
        'position',
    ];

    protected $casts = [
        'type' => WatermarkTypeEnum::class,
        'font_style' => FontStyleEnum::class,
        'text_transform' => TextTransformEnum::class,
        'border_style' => BorderStyleEnum::class,
        'position' => WatermarkPositionEnum::class,
        'line_height' => 'decimal:2',
        'letter_spacing' => 'decimal:2',
        'scale' => 'integer',
        'opacity' => 'integer',
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

    /**
     * Get the user that owns the watermark.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get all presets using this watermark as default.
     */
    public function presets(): HasMany
    {
        return $this->hasMany(MemoraPreset::class, 'default_watermark_uuid', 'uuid');
    }

    /**
     * Get all projects using this watermark.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(MemoraProject::class, 'watermark_uuid', 'uuid');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MemoraWatermarkFactory::new();
    }
}
