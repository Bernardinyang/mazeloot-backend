<?php

namespace Tests\Feature\Collections;

use App\Domains\Memora\Models\MemoraCollection;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
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
            'domain' => 'testuser',
        ]);
    }

    public function test_user_can_create_collection(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/memora/collections', [
            'name' => 'Test Collection',
            'description' => 'Test Description',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name'],
                'status',
                'statusText',
            ]);

        $this->assertDatabaseHas('memora_collections', [
            'name' => 'Test Collection',
            'user_uuid' => $this->user->uuid,
        ]);
    }

    public function test_user_can_list_own_collections(): void
    {
        MemoraCollection::factory()->count(3)->create(['user_uuid' => $this->user->uuid]);
        MemoraCollection::factory()->count(2)->create();

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/v1/memora/collections', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [],
                    'pagination',
                ],
            ]);
        
        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_user_can_update_collection(): void
    {
        $collection = MemoraCollection::factory()->create(['user_uuid' => $this->user->uuid]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->patchJson("/api/v1/memora/collections/{$collection->uuid}", [
            'name' => 'Updated Collection',
            'status' => 'active',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Collection')
            ->assertJsonPath('data.id', $collection->uuid);

        $this->assertDatabaseHas('memora_collections', [
            'uuid' => $collection->uuid,
            'name' => 'Updated Collection',
        ]);
    }

    public function test_user_can_publish_collection(): void
    {
        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'draft',
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->patchJson("/api/v1/memora/collections/{$collection->uuid}", [
            'status' => 'active',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $collection->uuid);
        
        $this->assertDatabaseHas('memora_collections', [
            'uuid' => $collection->uuid,
            'status' => 'active',
        ]);
    }

    public function test_public_collection_is_accessible(): void
    {
        // Clear cache and set domain
        app(\App\Services\Product\SubdomainResolutionService::class)->clearCache('testuser');
        UserProductPreference::where('user_uuid', $this->user->uuid)
            ->where('product_uuid', $this->product->uuid)
            ->update(['domain' => 'testuser']);
        
        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/memora/testuser/collections/{$collection->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $collection->uuid);
    }

    public function test_password_protected_collection_requires_password(): void
    {
        app(\App\Services\Product\SubdomainResolutionService::class)->clearCache('testuser');
        UserProductPreference::where('user_uuid', $this->user->uuid)
            ->where('product_uuid', $this->product->uuid)
            ->update(['domain' => 'testuser']);
        
        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'active',
            'settings' => [
                'privacy' => [
                    'collectionPasswordEnabled' => true,
                    'password' => 'secret123',
                ],
            ],
        ]);

        $response = $this->getJson("/api/v1/memora/testuser/collections/{$collection->uuid}");

        $response->assertStatus(401)
            ->assertJson(['code' => 'PASSWORD_REQUIRED']);
    }

    public function test_password_protected_collection_accessible_with_password(): void
    {
        app(\App\Services\Product\SubdomainResolutionService::class)->clearCache('testuser');
        UserProductPreference::where('user_uuid', $this->user->uuid)
            ->where('product_uuid', $this->product->uuid)
            ->update(['domain' => 'testuser']);
        
        $collection = MemoraCollection::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'active',
            'settings' => [
                'privacy' => [
                    'collectionPasswordEnabled' => true,
                    'password' => 'secret123',
                ],
            ],
        ]);

        $response = $this->getJson("/api/v1/memora/testuser/collections/{$collection->uuid}", [
            'X-Collection-Password' => 'secret123',
        ]);

        $response->assertStatus(200);
    }
}
