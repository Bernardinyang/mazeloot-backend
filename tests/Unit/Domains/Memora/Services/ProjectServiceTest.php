<?php

namespace Tests\Unit\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Services\ProjectService;
use App\Models\User;
use App\Services\Pagination\PaginationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectService $service;
    protected $mockPaginationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPaginationService = \Mockery::mock(PaginationService::class);
        $this->service = new ProjectService($this->mockPaginationService);
    }

    public function test_create_project(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $project = $this->service->create([
            'name' => 'Test Project',
            'description' => 'Test Description',
            'status' => 'draft',
        ], $user->uuid);

        $this->assertEquals('Test Project', $project->name);
        $this->assertEquals($user->uuid, $project->user_uuid);
        $this->assertEquals('draft', $project->status->value);
    }

    public function test_create_project_with_phases(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $project = $this->service->create([
            'name' => 'Test Project',
            'hasSelections' => true,
            'hasProofing' => true,
            'hasCollections' => true,
        ], $user->uuid);

        $this->assertTrue($project->has_selections);
        $this->assertTrue($project->has_proofing);
        $this->assertTrue($project->has_collections);
    }

    public function test_find_project(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $found = $this->service->find($project->uuid);

        $this->assertEquals($project->uuid, $found->uuid);
    }

    public function test_find_project_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $otherUser->uuid]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized');
        $this->service->find($project->uuid);
    }

    public function test_update_project(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $updated = $this->service->update($project->uuid, [
            'name' => 'Updated Name',
            'status' => 'active',
        ]);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('active', $updated->status->value);
    }

    public function test_delete_project(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $result = $this->service->delete($project->uuid);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('memora_projects', ['uuid' => $project->uuid]);
    }

    public function test_get_phases(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $project = MemoraProject::factory()->create([
            'user_uuid' => $user->uuid,
            'has_selections' => true,
            'has_proofing' => true,
        ]);

        $phases = $this->service->getPhases($project->uuid);

        $this->assertArrayHasKey('selection', $phases);
        $this->assertArrayHasKey('proofing', $phases);
        $this->assertArrayHasKey('collection', $phases);
    }

    public function test_toggle_star(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $result = $this->service->toggleStar($project->uuid);

        $this->assertTrue($result['starred']);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

