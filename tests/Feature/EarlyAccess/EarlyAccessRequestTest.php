<?php

namespace Tests\Feature\EarlyAccess;

use App\Enums\EarlyAccessRequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Jobs\NotifyAdminsEarlyAccessRequest;
use App\Models\EarlyAccessRequest;
use App\Models\EarlyAccessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EarlyAccessRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['role' => UserRoleEnum::ADMIN]);
    }

    public function test_user_can_submit_early_access_request()
    {
        Queue::fake();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/early-access/request', [
                'reason' => 'I want early access to test features',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'message',
                    'request_uuid',
                ],
            ]);

        $this->assertDatabaseHas('early_access_requests', [
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING->value,
            'reason' => 'I want early access to test features',
        ]);

        Queue::assertPushed(NotifyAdminsEarlyAccessRequest::class);
    }

    public function test_user_cannot_submit_duplicate_pending_request()
    {
        EarlyAccessRequest::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/early-access/request', [
                'reason' => 'Another request',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'You already have a pending early access request',
                'code' => 'PENDING_REQUEST_EXISTS',
            ]);
    }

    public function test_user_cannot_submit_request_if_already_has_early_access()
    {
        EarlyAccessUser::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/early-access/request');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'You already have early access',
                'code' => 'ALREADY_HAS_ACCESS',
            ]);
    }

    public function test_user_can_check_request_status()
    {
        $request = EarlyAccessRequest::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
            'reason' => 'Test reason',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/early-access/request/status');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'uuid' => $request->uuid,
                    'status' => 'pending',
                    'reason' => 'Test reason',
                ],
            ]);
    }

    public function test_user_gets_null_status_when_no_request_exists()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/early-access/request/status');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => null,
                    'message' => 'No early access request found',
                ],
            ]);
    }

    public function test_admin_can_list_early_access_requests()
    {
        EarlyAccessRequest::factory()->count(3)->create([
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/early-access/requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'uuid',
                            'user',
                            'status',
                            'reason',
                            'created_at',
                        ],
                    ],
                ],
            ]);
    }

    public function test_admin_can_view_single_request()
    {
        $request = EarlyAccessRequest::factory()->create([
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/early-access/requests/{$request->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'user',
                    'status',
                    'reason',
                ],
            ]);
    }

    public function test_admin_can_approve_request()
    {
        $request = EarlyAccessRequest::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/early-access/requests/{$request->uuid}/approve", [
                'discount_percentage' => 25,
                'storage_multiplier' => 2.0,
                'feature_flags' => ['ai_enhancement'],
                'priority_support' => true,
                'exclusive_badge' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'message',
                    'early_access' => [
                        'uuid',
                    ],
                ],
            ]);

        $request->refresh();
        $this->assertEquals(EarlyAccessRequestStatusEnum::APPROVED, $request->status);
        $this->assertEquals($this->admin->uuid, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);

        $this->assertDatabaseHas('early_access_users', [
            'user_uuid' => $this->user->uuid,
        ]);
    }

    public function test_admin_can_reject_request()
    {
        $request = EarlyAccessRequest::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => EarlyAccessRequestStatusEnum::PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/early-access/requests/{$request->uuid}/reject", [
                'rejection_reason' => 'Not eligible at this time',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'message' => 'Early access request rejected successfully',
                ],
            ]);

        $request->refresh();
        $this->assertEquals(EarlyAccessRequestStatusEnum::REJECTED, $request->status);
        $this->assertEquals($this->admin->uuid, $request->reviewed_by);
        $this->assertEquals('Not eligible at this time', $request->rejection_reason);
        $this->assertNotNull($request->reviewed_at);
    }

    public function test_admin_cannot_approve_already_processed_request()
    {
        $request = EarlyAccessRequest::factory()->create([
            'status' => EarlyAccessRequestStatusEnum::APPROVED,
            'reviewed_by' => $this->admin->uuid,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/early-access/requests/{$request->uuid}/approve", [
                'discount_percentage' => 25,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'code' => 'ALREADY_PROCESSED',
            ]);
    }

    public function test_non_admin_cannot_access_admin_endpoints()
    {
        $request = EarlyAccessRequest::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/early-access/requests');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_submit_request()
    {
        $response = $this->postJson('/api/v1/early-access/request');

        $response->assertStatus(401);
    }

    public function test_request_validation_works()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/early-access/request', [
                'reason' => str_repeat('a', 1001), // Exceeds max length
            ]);

        $response->assertStatus(422);
    }
}
