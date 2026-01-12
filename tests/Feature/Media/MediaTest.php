<?php

namespace Tests\Feature\Media;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Models\Product;
use App\Models\User;
use App\Models\UserFile;
use App\Models\UserProductPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
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

    public function test_user_can_upload_media(): void
    {
        $collection = MemoraCollection::factory()->create(['user_uuid' => $this->user->uuid]);
        $set = MemoraMediaSet::factory()->create([
            'collection_uuid' => $collection->uuid,
            'user_uuid' => $this->user->uuid,
        ]);

        // Create user file directly for testing
        $userFile = UserFile::factory()->create(['user_uuid' => $this->user->uuid]);
        $userFileUuid = $userFile->uuid;

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson("/api/v1/memora/collections/{$collection->uuid}/sets/{$set->uuid}/media", [
            'user_file_uuid' => $userFileUuid,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id'],
                'status',
                'statusText',
            ]);
    }

    public function test_user_can_list_media_in_set(): void
    {
        $collection = MemoraCollection::factory()->create(['user_uuid' => $this->user->uuid]);
        $set = MemoraMediaSet::factory()->create([
            'collection_uuid' => $collection->uuid,
            'user_uuid' => $this->user->uuid,
        ]);

        $file = UserFile::factory()->create(['user_uuid' => $this->user->uuid]);
        MemoraMedia::factory()->count(3)->create([
            'media_set_uuid' => $set->uuid,
            'user_uuid' => $this->user->uuid,
            'user_file_uuid' => $file->uuid,
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->getJson("/api/v1/memora/collections/{$collection->uuid}/sets/{$set->uuid}/media", [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => []]);
        
        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_delete_own_media(): void
    {
        $collection = MemoraCollection::factory()->create(['user_uuid' => $this->user->uuid]);
        $set = MemoraMediaSet::factory()->create([
            'collection_uuid' => $collection->uuid,
            'user_uuid' => $this->user->uuid,
        ]);

        $file = UserFile::factory()->create(['user_uuid' => $this->user->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'user_uuid' => $this->user->uuid,
            'user_file_uuid' => $file->uuid,
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->deleteJson("/api/v1/memora/media/{$media->uuid}", [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200);
        $this->assertSoftDeleted('memora_media', ['uuid' => $media->uuid]);
    }

    public function test_user_cannot_delete_other_users_media(): void
    {
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $collection = MemoraCollection::factory()->create(['user_uuid' => $otherUser->uuid]);
        $set = MemoraMediaSet::factory()->create([
            'collection_uuid' => $collection->uuid,
            'user_uuid' => $otherUser->uuid,
        ]);

        $file = UserFile::factory()->create(['user_uuid' => $otherUser->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'user_uuid' => $otherUser->uuid,
            'user_file_uuid' => $file->uuid,
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->deleteJson("/api/v1/memora/media/{$media->uuid}", [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('memora_media', ['uuid' => $media->uuid]);
    }

    public function test_upload_validates_file_type(): void
    {
        $collection = MemoraCollection::factory()->create(['user_uuid' => $this->user->uuid]);
        $set = MemoraMediaSet::factory()->create([
            'collection_uuid' => $collection->uuid,
            'user_uuid' => $this->user->uuid,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson("/api/v1/memora/collections/{$collection->uuid}/sets/{$set->uuid}/media", [
            'file' => $file,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_validates_file_size(): void
    {
        $collection = MemoraCollection::factory()->create(['user_uuid' => $this->user->uuid]);
        $set = MemoraMediaSet::factory()->create([
            'collection_uuid' => $collection->uuid,
            'user_uuid' => $this->user->uuid,
        ]);

        $file = UploadedFile::fake()->image('large.jpg')->size(300000); // 300MB

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->postJson("/api/v1/memora/collections/{$collection->uuid}/sets/{$set->uuid}/media", [
            'file' => $file,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(422);
    }
}
