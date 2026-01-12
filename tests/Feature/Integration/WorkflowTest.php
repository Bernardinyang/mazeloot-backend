<?php

namespace Tests\Feature\Integration;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraSelection;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        
        $this->product = Product::firstOrCreate(
            ['id' => 'memora'],
            ['id' => 'memora', 'name' => 'Memora', 'display_name' => 'Memora', 'slug' => 'memora', 'is_active' => true]
        );
        
        UserProductPreference::factory()->create([
            'user_uuid' => $this->user->uuid,
            'product_uuid' => $this->product->uuid,
        ]);
    }

    public function test_complete_project_workflow(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        // 1. Create project
        $projectResponse = $this->postJson('/api/v1/memora/projects', [
            'name' => 'Wedding Project',
            'hasSelections' => true,
            'hasProofing' => true,
            'hasCollections' => true,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $projectId = $projectResponse->json('data.id');
        $this->assertDatabaseHas('memora_projects', ['uuid' => $projectId]);

        // 2. Verify selection was created
        $selection = MemoraSelection::where('project_uuid', $projectId)->first();
        $this->assertNotNull($selection);

        // 3. Get project with all phases
        $projectResponse = $this->getJson("/api/v1/memora/projects/{$projectId}", [
            'Authorization' => "Bearer {$token}",
        ]);

        $projectResponse->assertStatus(200)
            ->assertJsonPath('data.hasSelections', true)
            ->assertJsonPath('data.hasProofing', true)
            ->assertJsonPath('data.hasCollections', true);
    }

    public function test_selection_to_proofing_workflow(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create project with selection
        $projectResponse = $this->postJson('/api/v1/memora/projects', [
            'name' => 'Test Project',
            'hasSelections' => true,
            'hasProofing' => true,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $projectId = $projectResponse->json('data.id');
        $selection = MemoraSelection::where('project_uuid', $projectId)->first();
        $this->assertNotNull($selection);
        $this->assertNotNull($selection->uuid);

        // Verify selection can be accessed via API (selections route doesn't have product slug prefix)
        $selectionResponse = $this->getJson("/api/v1/selections/{$selection->uuid}", [
            'Authorization' => "Bearer {$token}",
        ]);
        $selectionResponse->assertStatus(200);
    }

    public function test_collection_publishing_workflow(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create collection
        $collectionResponse = $this->postJson('/api/v1/memora/collections', [
            'name' => 'Portfolio Collection',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $collectionId = $collectionResponse->json('data.id');
        $this->assertNotNull($collectionId);

        // Publish collection
        $this->patchJson("/api/v1/memora/collections/{$collectionId}", [
            'status' => 'active',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $collection = MemoraCollection::find($collectionId);
        $this->assertNotNull($collection);
        $this->assertEquals('active', $collection->status->value);

        // Verify public access - need to set up domain
        app(\App\Services\Product\SubdomainResolutionService::class)->clearCache('testuser');
        UserProductPreference::where('user_uuid', $this->user->uuid)
            ->where('product_uuid', $this->product->uuid)
            ->update(['domain' => 'testuser']);
        
        $publicResponse = $this->getJson("/api/v1/memora/testuser/collections/{$collectionId}");
        $publicResponse->assertStatus(200);
    }

    public function test_project_with_all_phases(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $projectResponse = $this->postJson('/api/v1/memora/projects', [
            'name' => 'Full Workflow Project',
            'hasSelections' => true,
            'hasProofing' => true,
            'hasCollections' => true,
            'selectionSettings' => [
                'name' => 'Client Selection',
                'selectionLimit' => 50,
            ],
            'proofingSettings' => [
                'name' => 'Client Proofing',
                'maxRevisions' => 3,
            ],
            'collectionSettings' => [
                'name' => 'Final Collection',
            ],
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $projectId = $projectResponse->json('data.id');
        $project = MemoraProject::with(['selection', 'proofing', 'collection'])->find($projectId);
        $this->assertNotNull($project);

        $this->assertNotNull($project->selection);
        $this->assertNotNull($project->proofing);
        $this->assertNotNull($project->collection);
    }
}
