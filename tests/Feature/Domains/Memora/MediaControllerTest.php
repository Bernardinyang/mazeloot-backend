<?php

namespace Tests\Feature\Domains\Memora;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

use App\Models\Product;
use App\Models\UserProductPreference;

class MediaControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::firstOrCreate(
            ['id' => 'memora'],
            ['id' => 'memora', 'name' => 'Memora', 'display_name' => 'Memora', 'slug' => 'memora', 'is_active' => true]
        );
    }

    public function test_list_media(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        UserProductPreference::factory()->create([
            'user_uuid' => $user->uuid,
            'product_uuid' => $this->product->uuid,
        ]);
        Sanctum::actingAs($user);
        $collection = \App\Domains\Memora\Models\MemoraCollection::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['collection_uuid' => $collection->uuid]);
        MemoraMedia::factory()->count(3)->create(['media_set_uuid' => $set->uuid]);

        $response = $this->getJson("/api/v1/memora/collections/{$collection->uuid}/sets/{$set->uuid}/media");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => []]);
    }

    public function test_mark_media_selected(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        UserProductPreference::factory()->create([
            'user_uuid' => $user->uuid,
            'product_uuid' => $this->product->uuid,
        ]);
        Sanctum::actingAs($user);
        $selection = \App\Domains\Memora\Models\MemoraSelection::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['selection_uuid' => $selection->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_selected' => false,
        ]);

        // Test the service method directly (public route requires guest token)
        $mediaService = app(\App\Domains\Memora\Services\MediaService::class);
        $result = $mediaService->markSelected($media->uuid, true);
        
        $this->assertTrue($result->is_selected);
        $this->assertNotNull($result->selected_at);
    }

    public function test_get_media_revisions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        UserProductPreference::factory()->create([
            'user_uuid' => $user->uuid,
            'product_uuid' => $this->product->uuid,
        ]);
        Sanctum::actingAs($user);
        $set = MemoraMediaSet::factory()->create();
        $original = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'revision_number' => 0,
        ]);
        MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'original_media_uuid' => $original->uuid,
            'revision_number' => 1,
        ]);

        $response = $this->getJson("/api/v1/memora/media/{$original->uuid}/revisions");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => []]);
    }

    public function test_requires_authentication(): void
    {
        $set = MemoraMediaSet::factory()->create();
        $media = MemoraMedia::factory()->create(['media_set_uuid' => $set->uuid]);
        $response = $this->getJson("/api/v1/public/media/{$media->uuid}/revisions");
        $response->assertStatus(401);
    }
}
