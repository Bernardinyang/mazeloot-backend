<?php

namespace Tests\Unit\Services\EarlyAccess;

use App\Enums\EarlyAccessRequestStatusEnum;
use App\Events\EarlyAccessRequestUpdated;
use App\Jobs\NotifyAdminsEarlyAccessRequest;
use App\Models\EarlyAccessRequest;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\EarlyAccess\EarlyAccessRequestService;
use App\Services\EarlyAccess\EarlyAccessService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EarlyAccessRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EarlyAccessRequestService $service;

    protected User $user;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EarlyAccessRequestService::class);
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['role' => \App\Enums\UserRoleEnum::ADMIN]);
    }

    public function test_it_creates_an_early_access_request()
    {
        Queue::fake();
        Event::fake();
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([]);

        $request = $this->service->createRequest($this->user->uuid, 'Test reason');

        $this->assertInstanceOf(EarlyAccessRequest::class, $request);
        $this->assertEquals($this->user->uuid, $request->user_uuid);
        $this->assertEquals('Test reason', $request->reason);
        $this->assertEquals(EarlyAccessRequestStatusEnum::PENDING, $request->status);
        $this->assertNotNull($request->uuid);
    }

    public function test_it_creates_request_without_reason()
    {
        Queue::fake();
        Event::fake();
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([]);

        $request = $this->service->createRequest($this->user->uuid);

        $this->assertInstanceOf(EarlyAccessRequest::class, $request);
        $this->assertNull($request->reason);
        $this->assertEquals(EarlyAccessRequestStatusEnum::PENDING, $request->status);
    }

    public function test_it_dispatches_notification_job_when_creating_request()
    {
        Queue::fake();
        Event::fake();
        $adminUuids = [$this->admin->uuid];

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($adminUuids);

        $this->service->createRequest($this->user->uuid, 'Test reason');

        Queue::assertPushed(NotifyAdminsEarlyAccessRequest::class, function ($job) {
            return $job->requestUuid !== null
                && $job->userEmail === $this->user->email;
        });
    }

    public function test_it_broadcasts_event_when_creating_request()
    {
        Queue::fake();
        Event::fake();
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([]);

        $request = $this->service->createRequest($this->user->uuid);

        Event::assertDispatched(EarlyAccessRequestUpdated::class, function ($event) use ($request) {
            return $event->request->uuid === $request->uuid
                && $event->action === 'created';
        });
    }

    public function test_it_approves_request_and_grants_early_access()
    {
        Queue::fake();
        Event::fake();

        $request = EarlyAccessRequest::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $rewards = [
            'discount_percentage' => 25,
            'storage_multiplier' => 2.0,
            'feature_flags' => ['ai_enhancement'],
            'priority_support' => true,
            'exclusive_badge' => true,
        ];

        $earlyAccessService = $this->mock(EarlyAccessService::class);
        $earlyAccessService->shouldReceive('grantEarlyAccess')
            ->once()
            ->with($this->user->uuid, \Mockery::type('array'), \Mockery::any())
            ->andReturn(\App\Models\EarlyAccessUser::factory()->make());

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('create')
            ->once();

        $activityLogService = $this->mock(ActivityLogService::class);
        $activityLogService->shouldReceive('logQueued')
            ->once();

        $service = new EarlyAccessRequestService(
            $earlyAccessService,
            $notificationService,
            $activityLogService
        );

        $earlyAccess = $service->approveRequest($request, $this->admin, $rewards);

        $this->assertInstanceOf(\App\Models\EarlyAccessUser::class, $earlyAccess);
        $request->refresh();
        $this->assertEquals(EarlyAccessRequestStatusEnum::APPROVED, $request->status);
        $this->assertEquals($this->admin->uuid, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
    }

    public function test_it_rejects_request()
    {
        Event::fake();

        $request = EarlyAccessRequest::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $notificationService = $this->mock(NotificationService::class);
        $notificationService->shouldReceive('create')
            ->once();

        $activityLogService = $this->mock(ActivityLogService::class);
        $activityLogService->shouldReceive('logQueued')
            ->once();

        $service = new EarlyAccessRequestService(
            $this->mock(EarlyAccessService::class),
            $notificationService,
            $activityLogService
        );

        $service->rejectRequest($request, $this->admin, 'Not eligible');

        $request->refresh();
        $this->assertEquals(EarlyAccessRequestStatusEnum::REJECTED, $request->status);
        $this->assertEquals($this->admin->uuid, $request->reviewed_by);
        $this->assertEquals('Not eligible', $request->rejection_reason);
        $this->assertNotNull($request->reviewed_at);
    }

    public function test_it_validates_feature_flags_on_approval()
    {
        Queue::fake();
        Event::fake();

        config(['early_access.allowed_features' => ['ai_enhancement', 'advanced_export']]);

        $request = EarlyAccessRequest::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $rewards = [
            'feature_flags' => ['ai_enhancement', 'invalid_feature', 'advanced_export'],
        ];

        $earlyAccessService = $this->mock(EarlyAccessService::class);
        $earlyAccessService->shouldReceive('grantEarlyAccess')
            ->once()
            ->with(\Mockery::any(), \Mockery::on(function ($rewards) {
                return count($rewards['feature_flags']) === 2
                    && in_array('ai_enhancement', $rewards['feature_flags'])
                    && in_array('advanced_export', $rewards['feature_flags'])
                    && ! in_array('invalid_feature', $rewards['feature_flags']);
            }), \Mockery::any())
            ->andReturn(\App\Models\EarlyAccessUser::factory()->make());

        $service = new EarlyAccessRequestService(
            $earlyAccessService,
            $this->mock(NotificationService::class),
            $this->mock(ActivityLogService::class)
        );

        $service->approveRequest($request, $this->admin, $rewards);
    }
}
