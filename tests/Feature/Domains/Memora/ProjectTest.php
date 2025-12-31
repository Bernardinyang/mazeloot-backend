<?php

namespace Tests\Feature\Domains\Memora;

use App\Domains\Memora\Models\MemoraProject;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    public function test_user_can_list_projects(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        MemoraProject::factory()->count(3)->create(['user_uuid' => $user->uuid]);

        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Test Project',
            'description' => 'Test Description',
            'status' => 'draft',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('memora_projects', [
            'name' => 'Test Project',
            'user_uuid' => $user->uuid,
        ]);
    }

    public function test_user_can_view_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $response = $this->getJson("/api/v1/projects/{$project->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $project->uuid,
                    'name' => $project->name,
                ],
            ]);
    }

    public function test_user_can_update_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $response = $this->patchJson("/api/v1/projects/{$project->uuid}", [
            'name' => 'Updated Name',
            'status' => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $project->uuid,
                    'name' => 'Updated Name',
                    'status' => 'active',
                ],
            ]);
    }

    public function test_user_can_delete_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $response = $this->deleteJson("/api/v1/projects/{$project->uuid}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('memora_projects', [
            'uuid' => $project->uuid,
        ]);
    }

    public function test_projects_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(401);
    }
}
