<?php

namespace Tests\Feature\Domains\Memora;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProject;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    public function test_list_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['project_uuid' => $project->uuid]);
        MemoraMedia::factory()->count(3)->create(['media_set_uuid' => $set->uuid]);

        $response = $this->getJson("/api/v1/projects/{$project->uuid}/media?setId={$set->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['*' => ['id', 'url']]]);
    }

    public function test_mark_media_selected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $set = MemoraMediaSet::factory()->create();
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_selected' => false,
        ]);

        $response = $this->patchJson("/api/v1/media/{$media->uuid}/select", [
            'isSelected' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['is_selected' => true]]);
    }

    public function test_get_media_revisions(): void
    {
        $user = User::factory()->create();
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

        $response = $this->getJson("/api/v1/media/{$original->uuid}/revisions");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['revisions', 'original']]);
    }

    public function test_requires_authentication(): void
    {
        $set = MemoraMediaSet::factory()->create();
        $media = MemoraMedia::factory()->create(['media_set_uuid' => $set->uuid]);
        $response = $this->getJson("/api/v1/media/{$media->uuid}/revisions");
        $response->assertStatus(401);
    }
}
