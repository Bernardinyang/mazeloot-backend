<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'status_uuid',
        'role',
        'provider',
        'provider_id',
        'email_verified_at',
        'profile_photo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRoleEnum::class,
        ];
    }

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

            // Set default role to 'user' if not provided
            if (empty($model->role)) {
                $model->role = UserRoleEnum::USER;
            }
        });

        static::created(function ($model) {
            // Initialize default settings for new users
            try {
                $settingsService = app(\App\Domains\Memora\Services\SettingsService::class);
                $settingsService->initializeDefaults($model->uuid);
            } catch (\Exception $e) {
                // Log error but don't fail user creation
                Log::error('Failed to initialize default settings for user', [
                    'user_uuid' => $model->uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Get the activity logs for the user.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the user's status.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(UserStatus::class, 'status_uuid', 'uuid');
    }

    /**
     * Get the email verification codes for the user.
     */
    public function emailVerificationCodes(): HasMany
    {
        return $this->hasMany(EmailVerificationCode::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the password reset tokens for the user.
     */
    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class, 'user_uuid', 'uuid');
    }

    public function magicLinkTokens(): HasMany
    {
        return $this->hasMany(MagicLinkToken::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the selections starred by this user.
     */
    public function starredSelections(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Memora\Models\MemoraSelection::class,
            'user_starred_selections',
            'user_uuid',
            'selection_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Get the raw files starred by this user.
     */
    public function starredRawFiles(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Memora\Models\MemoraRawFile::class,
            'user_starred_raw_files',
            'user_uuid',
            'raw_file_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    public function starredProofing(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Memora\Models\MemoraProofing::class,
            'user_starred_proofing',
            'user_uuid',
            'proofing_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Get the media starred by this user.
     */
    public function starredMedia(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Memora\Models\MemoraMedia::class,
            'user_starred_media',
            'user_uuid',
            'media_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Get the projects starred by this user.
     */
    public function starredProjects(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Memora\Models\MemoraProject::class,
            'user_starred_projects',
            'user_uuid',
            'project_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Get the collections starred by this user.
     */
    public function starredCollections(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Memora\Models\MemoraCollection::class,
            'user_starred_collections',
            'user_uuid',
            'collection_uuid',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(UserRoleEnum $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user is an admin or super admin.
     */
    public function isAdmin(): bool
    {
        return $this->role && $this->role->isAdmin();
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRoleEnum::SUPER_ADMIN);
    }

    /**
     * Check if the user can manage admins (only super admins can).
     */
    public function canManageAdmins(): bool
    {
        return $this->role && $this->role->canManageAdmins();
    }

    /**
     * Check if the user can login based on their status.
     *
     * @return array{can_login: bool, message: string|null}
     */
    public function canLogin(): array
    {
        // Load status relationship if not already loaded
        if (! $this->relationLoaded('status')) {
            $this->load('status');
        }

        // If user has no status, allow login (status is nullable)
        if (! $this->status) {
            return ['can_login' => true, 'message' => null];
        }

        $statusName = strtolower($this->status->name);

        // Block login for these statuses
        $blockedStatuses = ['suspended', 'banned', 'blocked', 'inactive', 'deactivated'];

        if (in_array($statusName, $blockedStatuses)) {
            $message = match ($statusName) {
                'suspended' => 'Your account has been suspended. Please contact support for assistance.',
                'banned' => 'Your account has been banned. Please contact support for assistance.',
                'blocked' => 'Your account has been blocked. Please contact support for assistance.',
                'inactive', 'deactivated' => 'Your account is inactive. Please contact support to reactivate your account.',
                default => 'Your account status prevents login. Please contact support for assistance.',
            };

            return ['can_login' => false, 'message' => $message];
        }

        // Allow login for active status or any other status not in blocked list
        return ['can_login' => true, 'message' => null];
    }
}
