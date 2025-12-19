<?php

namespace App\Support\Traits;

use App\Models\ActivityLog;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait LogsActivity
{
    /**
     * Get the activity log service instance.
     */
    protected function activityLog(): ActivityLogService
    {
        return app(ActivityLogService::class);
    }

    /**
     * Log an activity with shorthand method.
     *
     * @param string $action
     * @param Model|null $subject
     * @param string|null $description
     * @param array|null $properties
     * @return ActivityLog
     */
    protected function logActivity(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $properties = null
    ): ActivityLog {
        return $this->activityLog()->log(
            $action,
            $subject,
            $description,
            $properties,
            null,
            request()
        );
    }

    /**
     * Log a creation activity.
     */
    protected function logCreated(Model $subject, ?string $description = null, ?array $properties = null): ActivityLog
    {
        return $this->activityLog()->logCreated($subject, $description, $properties, null, request());
    }

    /**
     * Log an update activity.
     */
    protected function logUpdated(Model $subject, ?string $description = null, ?array $properties = null): ActivityLog
    {
        return $this->activityLog()->logUpdated($subject, $description, $properties, null, request());
    }

    /**
     * Log a deletion activity.
     */
    protected function logDeleted(Model $subject, ?string $description = null, ?array $properties = null): ActivityLog
    {
        return $this->activityLog()->logDeleted($subject, $description, $properties, null, request());
    }

    /**
     * Log a view activity.
     */
    protected function logViewed(Model $subject, ?string $description = null, ?array $properties = null): ActivityLog
    {
        return $this->activityLog()->logViewed($subject, $description, $properties, null, request());
    }

    /**
     * Log a custom activity.
     */
    protected function logCustom(string $action, ?string $description = null, ?array $properties = null): ActivityLog
    {
        return $this->activityLog()->logCustom($action, $description, $properties, null, request());
    }
}

