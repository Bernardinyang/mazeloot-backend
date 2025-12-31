<?php

namespace App\Domains\Memora\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MemoraCoverLayout extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'memora_cover_layouts';

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
        'layout_config',
        'is_active',
        'is_default',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'layout_config' => 'array',
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
     * Get the default cover layout.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Scope a query to only include active cover layouts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order cover layouts by order column.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get the layout_config attribute.
     * Note: With Laravel's array cast, the accessor receives the RAW value (JSON string),
     * not the casted value. So we need to decode it ourselves.
     */
    public function getLayoutConfigAttribute($value): array
    {
        // If $value is a string, decode it (accessor receives raw value before cast)
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && ! empty($decoded)) {
                return $decoded;
            }
        }

        // If $value is already an array (shouldn't happen with cast, but handle it)
        if (is_array($value) && ! empty($value)) {
            return $value;
        }

        // Return defaults only if no value exists
        return [
            'layout' => 'stack',
            'media' => [
                'type' => 'image',
                'aspect_ratio' => '16:9',
                'fit' => 'cover',
                'bleed' => 'full',
                'max_width' => null,
            ],
            'content' => [
                'placement' => 'overlay',
                'alignment' => 'bottom-left',
            ],
            'overlay' => [
                'enabled' => true,
                'gradient' => 'bottom',
                'opacity' => 0.55,
            ],
            'spacing' => [
                'padding_x' => 80,
                'padding_y' => 60,
            ],
        ];
    }

    /**
     * Validate layout config structure.
     */
    public static function validateLayoutConfig(array $config): bool
    {
        $validLayouts = ['column', 'row', 'stack'];
        $validFits = ['cover', 'contain', 'fill'];
        $validBleeds = ['full', 'contained', 'none'];
        $validPlacements = ['overlay', 'below', 'above', 'side'];
        $validAlignments = [
            'top-left', 'top-center', 'top-right',
            'middle-left', 'middle-center', 'middle-right',
            'bottom-left', 'bottom-center', 'bottom-right',
        ];
        $validGradients = ['top', 'bottom', 'left', 'right', 'none'];

        // Validate layout type
        if (! isset($config['layout']) || ! in_array($config['layout'], $validLayouts)) {
            return false;
        }

        // Validate media
        if (isset($config['media'])) {
            $media = $config['media'];
            if (isset($media['fit']) && ! in_array($media['fit'], $validFits)) {
                return false;
            }
            if (isset($media['bleed']) && ! in_array($media['bleed'], $validBleeds)) {
                return false;
            }
            if (isset($media['max_width']) && ! is_null($media['max_width']) && (! is_numeric($media['max_width']) || $media['max_width'] < 0)) {
                return false;
            }
        }

        // Validate content
        if (isset($config['content'])) {
            $content = $config['content'];
            if (isset($content['placement']) && ! in_array($content['placement'], $validPlacements)) {
                return false;
            }
            if (isset($content['alignment']) && ! in_array($content['alignment'], $validAlignments)) {
                return false;
            }
        }

        // Validate overlay
        if (isset($config['overlay'])) {
            $overlay = $config['overlay'];
            if (isset($overlay['enabled']) && ! is_bool($overlay['enabled'])) {
                return false;
            }
            if (isset($overlay['gradient']) && ! in_array($overlay['gradient'], $validGradients)) {
                return false;
            }
            if (isset($overlay['opacity']) && (! is_numeric($overlay['opacity']) || $overlay['opacity'] < 0 || $overlay['opacity'] > 1)) {
                return false;
            }
        }

        // Validate spacing
        if (isset($config['spacing'])) {
            $spacing = $config['spacing'];
            if (isset($spacing['padding_x']) && (! is_int($spacing['padding_x']) || $spacing['padding_x'] < 0 || $spacing['padding_x'] > 200)) {
                return false;
            }
            if (isset($spacing['padding_y']) && (! is_int($spacing['padding_y']) || $spacing['padding_y'] < 0 || $spacing['padding_y'] > 200)) {
                return false;
            }
        }

        return true;
    }
}
