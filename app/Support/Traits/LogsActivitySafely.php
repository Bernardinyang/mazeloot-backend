<?php

namespace App\Support\Traits;

use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait LogsActivitySafely
{
    /**
     * Safely log an activity without breaking the main operation.
     */
    protected function logActivitySafely(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $properties = null,
        ?Model $causer = null,
        ?Request $request = null
    ): void {
        try {
            app(ActivityLogService::class)->logQueued(
                action: $action,
                subject: $subject,
                description: $description,
                properties: $properties,
                causer: $causer,
                request: $request
            );
        } catch (\Exception $e) {
            Log::warning('Activity logging failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Safely log a bulk operation.
     */
    protected function logBulkActivitySafely(
        string $action,
        string $description,
        int $count,
        ?string $phaseType = null,
        ?string $phaseUuid = null,
        ?array $properties = null,
        ?Model $causer = null,
        ?Request $request = null
    ): void {
        try {
            app(ActivityLogService::class)->logBulk(
                action: $action,
                description: $description,
                count: $count,
                phaseType: $phaseType,
                phaseUuid: $phaseUuid,
                properties: $properties,
                causer: $causer,
                request: $request
            );
        } catch (\Exception $e) {
            Log::warning('Bulk activity logging failed', [
                'action' => $action,
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
