<?php

namespace App\Domains\Memora\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemoraPreset extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_uuid',
        'name',
        'is_selected',
        'collection_tags',
        'photo_sets',
        'default_watermark_uuid',
        'email_registration',
        'gallery_assist',
        'slideshow',
        'social_sharing',
        'language',
        // Design fields
        'design_cover',
        'design_cover_focal_point',
        'design_font_family',
        'design_font_style',
        'design_color_palette',
        'design_grid_style',
        'design_grid_columns',
        'design_thumbnail_size',
        'design_grid_spacing',
        'design_navigation_style',
        'design_joy_cover_title',
        'design_joy_cover_avatar',
        'design_joy_cover_show_date',
        'design_joy_cover_show_name',
        'design_joy_cover_button_text',
        'design_joy_cover_show_button',
        'design_joy_cover_background_pattern',
        // Privacy fields
        'privacy_collection_password',
        'privacy_show_on_homepage',
        'privacy_client_exclusive_access',
        'privacy_allow_clients_mark_private',
        'privacy_client_only_sets',
        // Download fields
        'download_photo_download',
        'download_high_resolution_enabled',
        'download_high_resolution_size',
        'download_web_size_enabled',
        'download_web_size',
        'download_video_download',
        'download_download_pin',
        'download_download_pin_enabled',
        'download_limit_downloads',
        'download_download_limit',
        'download_restrict_to_contacts',
        'download_downloadable_sets',
        // Favorite fields
        'favorite_favorite_enabled',
        'favorite_favorite_photos',
        'favorite_favorite_notes',
    ];

    protected $casts = [
        'is_selected' => 'boolean',
        'photo_sets' => 'array',
        'email_registration' => 'boolean',
        'gallery_assist' => 'boolean',
        'slideshow' => 'boolean',
        'social_sharing' => 'boolean',
        // Design casts
        'design_cover_focal_point' => 'array',
        'design_joy_cover_show_date' => 'boolean',
        'design_joy_cover_show_name' => 'boolean',
        'design_joy_cover_show_button' => 'boolean',
        // Privacy casts
        'privacy_show_on_homepage' => 'boolean',
        'privacy_client_exclusive_access' => 'boolean',
        'privacy_allow_clients_mark_private' => 'boolean',
        'privacy_client_only_sets' => 'array',
        // Download casts
        'download_photo_download' => 'boolean',
        'download_high_resolution_enabled' => 'boolean',
        'download_web_size_enabled' => 'boolean',
        'download_video_download' => 'boolean',
        'download_download_pin' => 'boolean',
        'download_download_pin_enabled' => 'boolean',
        'download_limit_downloads' => 'boolean',
        'download_restrict_to_contacts' => 'boolean',
        'download_downloadable_sets' => 'array',
        // Favorite casts
        'favorite_favorite_enabled' => 'boolean',
        'favorite_favorite_photos' => 'boolean',
        'favorite_favorite_notes' => 'boolean',
    ];

    /**
     * Get the user that owns the preset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the default watermark for this preset.
     */
    public function defaultWatermark(): BelongsTo
    {
        return $this->belongsTo(MemoraWatermark::class, 'default_watermark_uuid', 'uuid');
    }

    /**
     * Get all projects using this preset.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(MemoraProject::class, 'preset_uuid', 'uuid');
    }
}
