<?php

namespace Tests\Unit\Jobs;

use App\Jobs\LogActivityJob;
use App\Services\ActivityLog\ActivityLogService;
use Mockery;
use Tests\TestCase;

class LogActivityJobTest extends TestCase
{
    public function test_job_calls_activity_log_service_process_queued_log(): void
    {
        $mockService = Mockery::mock(ActivityLogService::class);

        $mockService
            ->shouldReceive('processQueuedLog')
            ->once()
            ->with(
                'created',
                'App\Domains\Memora\Models\MemoraProject',
                'project-uuid-123',
                'Created MemoraProject',
                ['key' => 'value'],
                'App\Models\User',
                'user-uuid-456',
                'user-uuid-456',
                'projects.store',
                'POST',
                '127.0.0.1',
                'Mozilla/5.0'
            );

        $job = new LogActivityJob(
            action: 'created',
            subjectType: 'App\Domains\Memora\Models\MemoraProject',
            subjectUuid: 'project-uuid-123',
            description: 'Created MemoraProject',
            properties: ['key' => 'value'],
            causerType: 'App\Models\User',
            causerUuid: 'user-uuid-456',
            userUuid: 'user-uuid-456',
            route: 'projects.store',
            method: 'POST',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0'
        );

        $job->handle($mockService);

        $this->assertTrue(true); // Job executed without errors
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
