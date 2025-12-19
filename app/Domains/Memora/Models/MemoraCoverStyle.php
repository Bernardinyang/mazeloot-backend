<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MemoraCoverStyle extends Model
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
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
        'config',
        'preview_image_url',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'config' => 'array',
        'order' => 'integer',
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
     * Get the default cover style.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Get all presets using this cover style.
     */
    public function presets(): HasMany
    {
        return $this->hasMany(MemoraPreset::class, 'design_cover_uuid', 'uuid');
    }

    /**
     * Scope a query to only include active cover styles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order cover styles by order column.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get the config attribute with defaults if null.
     * Note: This accessor runs after the cast, so $value is already an array.
     */
    public function getConfigAttribute($value): array
    {
        // If value is null or empty, return default structure
        if (empty($value) || !is_array($value)) {
            // Only use $this->slug and $this->name if they exist (to avoid errors during creation)
            $slug = $this->attributes['slug'] ?? $this->slug ?? null;
            $name = $this->attributes['name'] ?? $this->name ?? 'Default';
            
            return [
                'id' => $slug,
                'label' => $name,
                'textPosition' => 'center',
                'textAlignment' => 'center',
                'borders' => [
                    'enabled' => false,
                    'sides' => [],
                    'width' => 0,
                    'style' => 'solid',
                    'color' => 'accent',
                    'radius' => 0,
                ],
                'lines' => [
                    'horizontal' => [],
                    'vertical' => [],
                ],
                'dividers' => [
                    'enabled' => false,
                    'type' => 'vertical',
                    'position' => 0,
                    'width' => 0,
                    'color' => 'accent',
                    'style' => 'solid',
                ],
                'frame' => [
                    'enabled' => false,
                    'type' => 'full',
                    'sides' => [],
                    'width' => 0,
                    'color' => 'accent',
                    'padding' => 0,
                    'radius' => 0,
                ],
                'backgroundSections' => [],
                'decorations' => [],
                'specialLayout' => null,
            ];
        }

        return $value;
    }
}

