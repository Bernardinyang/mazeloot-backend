<?php

namespace Tests\Unit\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Services\ProofingService;
use App\Models\User;
use App\Models\UserFile;
use App\Services\Pagination\PaginationService;
use App\Services\Upload\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProofingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProofingService $service;

    protected $mockUploadService;

    protected $mockPaginationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUploadService = \Mockery::mock(UploadService::class);
        $this->mockPaginationService = \Mockery::mock(PaginationService::class);
        $mockNotificationService = \Mockery::mock(\App\Services\Notification\NotificationService::class);
        $mockNotificationService->shouldReceive('create')->andReturn(new \App\Models\Notification);
        $mockActivityLogService = \Mockery::mock(\App\Services\ActivityLog\ActivityLogService::class);
        $mockActivityLogService->shouldReceive('log')->andReturn(new \App\Models\ActivityLog);
        $this->service = new ProofingService($this->mockUploadService, $this->mockPaginationService, $mockNotificationService, $mockActivityLogService);
    }

    public function test_create_proofing_standalone(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);

        $proofing = $this->service->create([
            'name' => 'Test Proofing',
            'description' => 'Test Description',
            'maxRevisions' => 3,
        ]);

        $this->assertInstanceOf(MemoraProofing::class, $proofing);
        $this->assertEquals('Test Proofing', $proofing->name);
        $this->assertEquals('Test Description', $proofing->description);
        $this->assertEquals(3, $proofing->max_revisions);
        $this->assertEquals($user->uuid, $proofing->user_uuid);
        $this->assertNull($proofing->project_uuid);
    }

    public function test_create_proofing_with_project(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $proofing = $this->service->create([
            'project_uuid' => $project->uuid,
            'name' => 'Project Proofing',
        ]);

        $this->assertEquals($project->uuid, $proofing->project_uuid);
        $this->assertNotNull($proofing->color);
    }

    public function test_create_requires_authentication(): void
    {
        Auth::logout();

        $this->expectException(\Illuminate\Auth\AuthenticationException::class);
        $this->service->create(['name' => 'Test']);
    }

    public function test_find_proofing(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);

        $found = $this->service->find(null, $proofing->uuid);

        $this->assertEquals($proofing->uuid, $found->uuid);
    }

    public function test_find_proofing_with_project(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);

        $found = $this->service->find($project->uuid, $proofing->uuid);

        $this->assertEquals($proofing->uuid, $found->uuid);
    }

    public function test_complete_proofing(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
            'status' => 'draft',
        ]);

        $completed = $this->service->complete($project->uuid, $proofing->uuid);

        $this->assertEquals('completed', $completed->status->value);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_update_proofing(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);

        $updated = $this->service->update(null, $proofing->uuid, [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'maxRevisions' => 10,
        ]);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated Description', $updated->description);
        $this->assertEquals(10, $updated->max_revisions);
    }

    public function test_update_allowed_emails(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);

        $updated = $this->service->update(null, $proofing->uuid, [
            'allowedEmails' => ['test@example.com', 'user@example.com'],
        ]);

        $this->assertCount(2, $updated->allowed_emails);
        $this->assertContains('test@example.com', $updated->allowed_emails);
    }

    public function test_update_primary_email(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'allowed_emails' => ['test@example.com'],
        ]);

        $updated = $this->service->update(null, $proofing->uuid, [
            'primaryEmail' => 'test@example.com',
        ]);

        $this->assertEquals('test@example.com', $updated->primary_email);
    }

    public function test_update_primary_email_adds_to_allowed(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);

        $updated = $this->service->update(null, $proofing->uuid, [
            'primaryEmail' => 'new@example.com',
        ]);

        $this->assertEquals('new@example.com', $updated->primary_email);
        $this->assertContains('new@example.com', $updated->allowed_emails);
    }

    public function test_upload_revision_requires_ready_media(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_ready_for_revision' => false,
        ]);
        $userFile = UserFile::factory()->create(['user_uuid' => $user->uuid]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Media is not ready for revision');
        $this->service->uploadRevision($project->uuid, $proofing->uuid, $media->uuid, 1, 'Test', $userFile->uuid);
    }

    public function test_upload_revision_respects_max_limit(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
            'max_revisions' => 2,
        ]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_ready_for_revision' => true,
            'revision_number' => 0,
        ]);
        $userFile = UserFile::factory()->create(['user_uuid' => $user->uuid]);

        $revision1 = $this->service->uploadRevision($project->uuid, $proofing->uuid, $media->uuid, 1, 'Rev 1', $userFile->uuid);

        // After first revision, mark the new revision as ready for next revision
        $revision1->update(['is_ready_for_revision' => true]);

        $revision2 = $this->service->uploadRevision($project->uuid, $proofing->uuid, $revision1->uuid, 2, 'Rev 2', $userFile->uuid);

        // Mark revision 2 as ready
        $revision2->update(['is_ready_for_revision' => true]);

        // Third revision should fail due to max limit (2)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Maximum revision limit');
        $this->service->uploadRevision($project->uuid, $proofing->uuid, $revision2->uuid, 3, 'Rev 3', $userFile->uuid);
    }

    public function test_delete_proofing(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);

        $result = $this->service->delete(null, $proofing->uuid);

        $this->assertTrue($result);
        $this->assertSoftDeleted('memora_proofing', ['uuid' => $proofing->uuid]);
    }

    public function test_publish_proofing(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
            'status' => 'draft',
            'allowed_emails' => ['test@example.com'],
        ]);

        $published = $this->service->publish($project->uuid, $proofing->uuid);

        $this->assertEquals('active', $published->status->value);
    }

    public function test_toggle_star(): void
    {
        $user = User::factory()->create(['memora_tier' => 'pro']);
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);

        $result = $this->service->toggleStar(null, $proofing->uuid);

        $this->assertTrue($result['starred']);
        $this->assertTrue($proofing->fresh()->starredByUsers->contains($user->uuid));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
