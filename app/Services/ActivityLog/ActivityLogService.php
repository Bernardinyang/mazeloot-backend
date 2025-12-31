<?php

namespace App\Services\ActivityLog;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Log an activity.
     *
     * @param  string  $action  Action name (e.g., 'created', 'updated', 'deleted', 'viewed')
     * @param  Model|null  $subject  The model being acted upon
     * @param  string|null  $description  Human-readable description
     * @param  array|null  $properties  Additional metadata
     * @param  Model|null  $causer  The model that caused the activity (defaults to authenticated user)
     * @param  Request|null  $request  The request object to extract IP, user agent, etc.
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $properties = null,
        ?Model $causer = null,
        ?Request $request = null
    ): ActivityLog {
        // Determine the causer (user who performed the action)
        $userUuid = null;
        $causerType = null;
        $causerUuid = null;

        if ($causer) {
            $causerType = get_class($causer);
            $causerUuid = $causer->getKey();

            // If causer is a User, also set user_uuid for easier querying (use uuid column)
            if ($causer instanceof \App\Models\User) {
                $userUuid = $causer->uuid;
            }
        } elseif (Auth::check()) {
            $causer = Auth::user();
            $userUuid = $causer->uuid;
            $causerType = get_class($causer);
            $causerUuid = $causer->uuid;
        }

        // Extract request information
        $request = $request ?? request();
        $route = $request ? ($request->route()?->getName() ?? $request->path()) : null;
        $method = $request?->method();
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();

        // Build description if not provided
        if (! $description && $subject) {
            $subjectName = class_basename($subject);
            $description = ucfirst($action).' '.$subjectName;
            if ($subject->getKey()) {
                $description .= ' #'.$subject->getKey();
            }
        }

        // Activity logging is fast (simple DB insert), so we do it synchronously
        // For bulk operations, use logQueued() method instead
        return ActivityLog::create([
            'user_uuid' => $userUuid,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_uuid' => $subject?->getKey(),
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'route' => $route,
            'method' => $method,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'causer_type' => $causerType,
            'causer_uuid' => $causerUuid,
        ]);
    }

    /**
     * Log a model creation activity.
     */
    public function logCreated(Model $subject, ?string $description = null, ?array $properties = null, ?Model $causer = null, ?Request $request = null): ActivityLog
    {
        return $this->log('created', $subject, $description, $properties, $causer, $request);
    }

    /**
     * Log a model update activity.
     */
    public function logUpdated(Model $subject, ?string $description = null, ?array $properties = null, ?Model $causer = null, ?Request $request = null): ActivityLog
    {
        return $this->log('updated', $subject, $description, $properties, $causer, $request);
    }

    /**
     * Log a model deletion activity.
     */
    public function logDeleted(Model $subject, ?string $description = null, ?array $properties = null, ?Model $causer = null, ?Request $request = null): ActivityLog
    {
        return $this->log('deleted', $subject, $description, $properties, $causer, $request);
    }

    /**
     * Log a model view/read activity.
     */
    public function logViewed(Model $subject, ?string $description = null, ?array $properties = null, ?Model $causer = null, ?Request $request = null): ActivityLog
    {
        return $this->log('viewed', $subject, $description, $properties, $causer, $request);
    }

    /**
     * Log a custom activity without a subject.
     */
    public function logCustom(string $action, ?string $description = null, ?array $properties = null, ?Model $causer = null, ?Request $request = null): ActivityLog
    {
        return $this->log($action, null, $description, $properties, $causer, $request);
    }

    /**
     * Get activities for a specific user.
     */
    public function getActivitiesForUser($userUuid, int $limit = 50)
    {
        return ActivityLog::forUser($userUuid)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get activities for a specific subject.
     */
    public function getActivitiesForSubject(Model $subject, int $limit = 50)
    {
        return ActivityLog::forSubject(get_class($subject), $subject->getKey())
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get activities by action.
     */
    public function getActivitiesByAction(string $action, int $limit = 50)
    {
        return ActivityLog::forAction($action)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Queue activity logging (for bulk operations or when non-blocking is needed).
     */
    public function logQueued(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $properties = null,
        ?Model $causer = null,
        ?Request $request = null
    ): void {
        // Determine the causer (user who performed the action)
        $userUuid = null;
        $causerType = null;
        $causerUuid = null;

        if ($causer) {
            $causerType = get_class($causer);
            $causerUuid = $causer->getKey();

            if ($causer instanceof \App\Models\User) {
                $userUuid = $causer->uuid;
            }
        } elseif (Auth::check()) {
            $causer = Auth::user();
            $userUuid = $causer->uuid;
            $causerType = get_class($causer);
            $causerUuid = $causer->uuid;
        }

        // Extract request information
        $request = $request ?? request();
        $route = $request ? ($request->route()?->getName() ?? $request->path()) : null;
        $method = $request?->method();
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();

        // Build description if not provided
        if (! $description && $subject) {
            $subjectName = class_basename($subject);
            $description = ucfirst($action).' '.$subjectName;
            if ($subject->getKey()) {
                $description .= ' #'.$subject->getKey();
            }
        }

        // Dispatch job to queue
        \App\Jobs\LogActivityJob::dispatch(
            action: $action,
            subjectType: $subject ? get_class($subject) : null,
            subjectUuid: $subject?->getKey(),
            description: $description,
            properties: $properties,
            causerType: $causerType,
            causerUuid: $causerUuid,
            userUuid: $userUuid,
            route: $route,
            method: $method,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Process queued activity log (called by LogActivityJob).
     */
    public function processQueuedLog(
        string $action,
        ?string $subjectType = null,
        ?string $subjectUuid = null,
        ?string $description = null,
        ?array $properties = null,
        ?string $causerType = null,
        ?string $causerUuid = null,
        ?string $userUuid = null,
        ?string $route = null,
        ?string $method = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        ActivityLog::create([
            'user_uuid' => $userUuid,
            'subject_type' => $subjectType,
            'subject_uuid' => $subjectUuid,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'route' => $route,
            'method' => $method,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'causer_type' => $causerType,
            'causer_uuid' => $causerUuid,
        ]);
    }
}
