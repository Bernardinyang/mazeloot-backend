<?php

namespace App\Domains\Memora\Models;

use App\Domains\Memora\Enums\BorderStyle;
use App\Domains\Memora\Enums\FontStyle;
use App\Domains\Memora\Enums\TextTransform;
use App\Domains\Memora\Enums\WatermarkPosition;
use App\Domains\Memora\Enums\WatermarkType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Watermark extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'memora_watermarks';

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
        'type' => WatermarkType::class,
        'font_style' => FontStyle::class,
        'text_transform' => TextTransform::class,
        'border_style' => BorderStyle::class,
        'position' => WatermarkPosition::class,
        'line_height' => 'decimal:2',
        'letter_spacing' => 'decimal:2',
        'scale' => 'integer',
        'opacity' => 'integer',
    ];

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
        return $this->hasMany(Preset::class, 'default_watermark_uuid', 'uuid');
    }

    /**
     * Get all projects using this watermark.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'watermark_uuid', 'uuid');
    }
}
