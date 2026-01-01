<?php

namespace App\Domains\Memora\Models;

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MemoraSettings extends Model
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
        'branding_domain',
        'branding_custom_domain',
        'branding_logo_uuid',
        'branding_favicon_uuid',
        'branding_show_mazeloot_branding',
        'preference_filename_display',
        'preference_search_engine_visibility',
        'preference_sharpening_level',
        'preference_raw_photo_support',
        'preference_terms_of_service',
        'preference_privacy_policy',
        'preference_enable_cookie_banner',
        'preference_language',
        'preference_timezone',
        'homepage_status',
        'homepage_password',
        'homepage_biography',
        'homepage_info',
        'email_from_name',
        'email_from_address',
        'email_reply_to',
    ];

    protected $casts = [
        'branding_show_mazeloot_branding' => 'boolean',
        'preference_raw_photo_support' => 'boolean',
        'preference_enable_cookie_banner' => 'boolean',
        'homepage_status' => 'boolean',
        'homepage_info' => 'array',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(UserFile::class, 'branding_logo_uuid', 'uuid');
    }

    public function favicon(): BelongsTo
    {
        return $this->belongsTo(UserFile::class, 'branding_favicon_uuid', 'uuid');
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(MemoraSocialLink::class, 'user_uuid', 'user_uuid');
    }
}
