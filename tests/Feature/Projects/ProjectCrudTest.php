<?php

namespace Tests\Feature\Projects;

use App\Domains\Memora\Models\MemoraProject;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        
        // Create memora product
        $this->product = Product::factory()->create([
            'slug' => 'memora',
            'is_active' => true,
        ]);
        
        // Link user to product
        UserProductPreference::factory()->create([
            'user_uuid' => $this->user->uuid,
            'product_uuid' => $this->product->uuid,
        ]);
    }

    public function test_user_can_create_project(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/memora/projects', [
            'name' => 'Test Project',
            'description' => 'Test Description',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                ],
                'status',
                'statusText',
            ]);

        $this->assertDatabaseHas('memora_projects', [
            'name' => 'Test Project',
            'user_uuid' => $this->user->uuid,
        ]);
    }

    public function test_user_can_list_own_projects(): void
    {
        MemoraProject::factory()->count(3)->create(['user_uuid' => $this->user->uuid]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        MemoraProject::factory()->count(2)->create(['user_uuid' => $otherUser->uuid]); // Other user's projects

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/v1/memora/projects', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [],
                    'pagination',
                ],
            ]);
        
        // Service should filter by authenticated user, so we should get only our 3 projects
        $projects = $response->json('data.data');
        $this->assertCount(3, $projects);
    }

    public function test_user_can_view_own_project(): void
    {
        $project = MemoraProject::factory()->create(['user_uuid' => $this->user->uuid]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->getJson("/api/v1/memora/projects/{$project->uuid}", [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $project->uuid)
            ->assertJsonPath('data.name', $project->name);
    }

    public function test_user_can_update_own_project(): void
    {
        $project = MemoraProject::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name' => 'Original Name',
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->patchJson("/api/v1/memora/projects/{$project->uuid}", [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.id', $project->uuid);

        $this->assertDatabaseHas('memora_projects', [
            'uuid' => $project->uuid,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_delete_own_project(): void
    {
        $project = MemoraProject::factory()->create(['user_uuid' => $this->user->uuid]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->deleteJson("/api/v1/memora/projects/{$project->uuid}", [], [
            'Authorization' => "Bearer {$token}",
        ]);

        // API returns 200 with status 204 in body
        $response->assertStatus(200)
            ->assertJsonPath('status', 204);

        // Project should be hard deleted (forceDelete)
        $this->assertDatabaseMissing('memora_projects', [
            'uuid' => $project->uuid,
        ]);
    }

    public function test_project_creation_validates_required_fields(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/memora/projects', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(422);
    }

    public function test_project_name_has_max_length(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/memora/projects', [
            'name' => str_repeat('a', 256),
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(422);
    }

    public function test_project_can_have_phase_settings(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/memora/projects', [
            'name' => 'Project with Phases',
            'hasSelections' => true,
            'hasProofing' => true,
            'hasCollections' => true,
            'selectionSettings' => [
                'name' => 'Selection Phase',
                'selectionLimit' => 10,
            ],
            'proofingSettings' => [
                'name' => 'Proofing Phase',
                'maxRevisions' => 5,
            ],
            'collectionSettings' => [
                'name' => 'Collection Phase',
            ],
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(201);
        $project = MemoraProject::find($response->json('data.id'));
        $this->assertTrue($project->has_selections);
        $this->assertTrue($project->has_proofing);
        $this->assertTrue($project->has_collections);
    }
}
