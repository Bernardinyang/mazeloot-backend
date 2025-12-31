<?php

namespace Tests\Unit\Services\ActivityLog;

use App\Models\ActivityLog;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogServiceProcessQueuedLogTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityLogService $activityLogService;

    public function test_process_queued_log_creates_activity_log(): void
    {
        // Use null user_uuid since it's nullable and UUID() function doesn't work in SQLite
        $this->activityLogService->processQueuedLog(
            action: 'created',
            subjectType: 'App\Domains\Memora\Models\MemoraProject',
            subjectUuid: 'project-uuid-123',
            description: 'Created MemoraProject',
            properties: ['key' => 'value'],
            causerType: 'App\Models\User',
            causerUuid: 'user-uuid-456',
            userUuid: null, // Nullable, skip FK constraint for test
            route: 'projects.store',
            method: 'POST',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'subject_type' => 'App\Domains\Memora\Models\MemoraProject',
            'subject_uuid' => 'project-uuid-123',
            'description' => 'Created MemoraProject',
            'causer_type' => 'App\Models\User',
            'causer_uuid' => 'user-uuid-456',
            'user_uuid' => null,
            'route' => 'projects.store',
            'method' => 'POST',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);
    }

    public function test_process_queued_log_handles_nullable_fields(): void
    {
        $this->activityLogService->processQueuedLog(
            action: 'viewed',
            subjectType: null,
            subjectUuid: null,
            description: 'Viewed dashboard',
            properties: null,
            causerType: null,
            causerUuid: null,
            userUuid: null,
            route: null,
            method: null,
            ipAddress: null,
            userAgent: null
        );

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'viewed',
            'description' => 'Viewed dashboard',
        ]);
    }

    public function test_process_queued_log_stores_properties_as_json(): void
    {
        $properties = ['key1' => 'value1', 'key2' => 'value2'];

        // user_uuid can be null, so no need to create a user
        $this->activityLogService->processQueuedLog(
            action: 'updated',
            properties: $properties
        );

        $log = ActivityLog::where('action', 'updated')->first();
        $this->assertNotNull($log);
        $this->assertEquals($properties, $log->properties);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityLogService = app(ActivityLogService::class);
    }
}
