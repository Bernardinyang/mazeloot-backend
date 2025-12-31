<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_uuid',
        'subject_type',
        'subject_uuid',
        'action',
        'description',
        'properties',
        'route',
        'method',
        'ip_address',
        'user_agent',
        'causer_type',
        'causer_uuid',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the subject (polymorphic model) that was acted upon.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_uuid');
    }

    /**
     * Get the causer (polymorphic model) that caused the activity.
     */
    public function causer(): MorphTo
    {
        return $this->morphTo('causer', 'causer_type', 'causer_uuid');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by subject type.
     */
    public function scopeForSubject($query, string $subjectType, $subjectUuid = null)
    {
        $query->where('subject_type', $subjectType);

        if ($subjectUuid !== null) {
            $query->where('subject_uuid', $subjectUuid);
        }

        return $query;
    }

    /**
     * Scope to get recent activities.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
