<?php

namespace Tests\Feature\Domains\Memora;

use Tests\TestCase;
use App\Models\User;
use App\Domains\Memora\Models\Project;
use Laravel\Sanctum\Sanctum;

class ProjectTest extends TestCase
{
    public function test_user_can_list_projects(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Project::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'createdAt',
                        'updatedAt',
                    ],
                ],
            ]);
    }

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/projects', [
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
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_view_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $project->id,
                    'name' => $project->name,
                ],
            ]);
    }

    public function test_user_can_update_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->patchJson("/api/projects/{$project->id}", [
            'name' => 'Updated Name',
            'status' => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $project->id,
                    'name' => 'Updated Name',
                    'status' => 'active',
                ],
            ]);
    }

    public function test_user_can_delete_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(200); // ApiResponse returns 200 even for 204 status

        $this->assertDatabaseMissing('memora_projects', [
            'id' => $project->id,
        ]);
    }

    public function test_projects_require_authentication(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertStatus(401);
    }
}
