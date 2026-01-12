<?php

namespace Tests\Feature\Auth;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraProject;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create memora product (use firstOrCreate to avoid duplicates)
        $this->product = Product::firstOrCreate(
            ['id' => 'memora'],
            ['id' => 'memora', 'name' => 'Memora', 'display_name' => 'Memora', 'slug' => 'memora', 'is_active' => true]
        );
        
        // Create helper to setup product for user
        $product = $this->product;
        $this->setupProductForUser = function (User $user) use ($product) {
            UserProductPreference::firstOrCreate(
                [
                    'user_uuid' => $user->uuid,
                    'product_uuid' => $product->uuid,
                ]
            );
        };
    }

    public function test_user_can_only_access_own_projects(): void
    {
        $user1 = User::factory()->create(['email_verified_at' => now()]);
        $user2 = User::factory()->create(['email_verified_at' => now()]);
        
        ($this->setupProductForUser)($user1);
        ($this->setupProductForUser)($user2);

        $project = MemoraProject::factory()->create(['user_uuid' => $user1->uuid]);

        $token = $user2->createToken('test-token')->plainTextToken;

        $response = $this->getJson("/api/v1/memora/projects/{$project->uuid}", [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_access_own_projects(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        ($this->setupProductForUser)($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson("/api/v1/memora/projects/{$project->uuid}", [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200);
    }

    public function test_user_cannot_update_other_users_projects(): void
    {
        $user1 = User::factory()->create(['email_verified_at' => now()]);
        $user2 = User::factory()->create(['email_verified_at' => now()]);
        
        ($this->setupProductForUser)($user1);
        ($this->setupProductForUser)($user2);

        $project = MemoraProject::factory()->create(['user_uuid' => $user1->uuid]);

        $token = $user2->createToken('test-token')->plainTextToken;

        $response = $this->patchJson("/api/v1/memora/projects/{$project->uuid}", [
            'name' => 'Hacked Project',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_delete_other_users_projects(): void
    {
        $user1 = User::factory()->create(['email_verified_at' => now()]);
        $user2 = User::factory()->create(['email_verified_at' => now()]);
        
        ($this->setupProductForUser)($user1);
        ($this->setupProductForUser)($user2);

        $project = MemoraProject::factory()->create(['user_uuid' => $user1->uuid]);

        $token = $user2->createToken('test-token')->plainTextToken;

        $response = $this->deleteJson("/api/v1/memora/projects/{$project->uuid}", [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_only_access_own_collections(): void
    {
        $user1 = User::factory()->create(['email_verified_at' => now()]);
        $user2 = User::factory()->create(['email_verified_at' => now()]);
        
        ($this->setupProductForUser)($user1);
        ($this->setupProductForUser)($user2);

        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $user1->uuid,
            'status' => 'draft',
        ]);

        $token = $user2->createToken('test-token')->plainTextToken;

        $response = $this->getJson("/api/v1/memora/collections/{$collection->uuid}", [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
    }

    public function test_public_collections_are_accessible_without_auth(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        
        // Set up domain for subdomain resolution
        UserProductPreference::factory()->create([
            'user_uuid' => $user->uuid,
            'product_uuid' => $this->product->uuid,
            'domain' => 'testuser',
        ]);
        
        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $user->uuid,
            'status' => 'active',
        ]);

        // Public route uses subdomainOrUsername
        $response = $this->getJson("/api/v1/memora/testuser/collections/{$collection->uuid}");

        $response->assertStatus(200);
    }

    public function test_draft_collections_are_not_publicly_accessible(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        
        UserProductPreference::factory()->create([
            'user_uuid' => $user->uuid,
            'product_uuid' => $this->product->uuid,
            'domain' => 'testuser',
        ]);
        
        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $user->uuid,
            'status' => 'draft',
        ]);

        $response = $this->getJson("/api/v1/memora/testuser/collections/{$collection->uuid}");

        $response->assertStatus(403);
    }

    public function test_owner_can_access_draft_collections(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        
        UserProductPreference::factory()->create([
            'user_uuid' => $user->uuid,
            'product_uuid' => $this->product->uuid,
            'domain' => 'testuser',
        ]);
        
        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $user->uuid,
            'status' => 'draft',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson("/api/v1/memora/collections/{$collection->uuid}", [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200);
    }
}
