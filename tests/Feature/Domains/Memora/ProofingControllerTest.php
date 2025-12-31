<?php

namespace Tests\Feature\Domains\Memora;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraProject;
use App\Models\User;
use App\Models\UserFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProofingControllerTest extends TestCase
{
    public function test_list_proofings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        MemoraProofing::factory()->count(3)->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);

        $response = $this->getJson("/api/v1/proofing?project_uuid={$project->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'status'],
                    ],
                ],
            ]);
    }

    public function test_create_proofing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);

        $response = $this->postJson("/api/v1/projects/{$project->uuid}/proofing", [
            'name' => 'Test Proofing',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name']]);

        $this->assertDatabaseHas('memora_proofing', [
            'name' => 'Test Proofing',
            'project_uuid' => $project->uuid,
        ]);
    }

    public function test_show_proofing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->uuid}/proofing/{$proofing->uuid}");

        $response->assertStatus(200)
            ->assertJson(['data' => ['id' => $proofing->uuid]]);
    }

    public function test_update_proofing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);

        $response = $this->patchJson("/api/v1/projects/{$project->uuid}/proofing/{$proofing->uuid}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['name' => 'Updated Name']]);
    }

    public function test_delete_proofing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);

        $response = $this->deleteJson("/api/v1/projects/{$project->uuid}/proofing/{$proofing->uuid}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('memora_proofing', ['uuid' => $proofing->uuid]);
    }

    public function test_upload_revision(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_ready_for_revision' => true,
        ]);
        $userFile = UserFile::factory()->create(['user_uuid' => $user->uuid]);

        $response = $this->postJson("/api/v1/projects/{$project->uuid}/proofing/{$proofing->uuid}/revisions", [
            'mediaId' => $media->uuid,
            'revisionNumber' => 1,
            'description' => 'Test Revision',
            'userFileUuid' => $userFile->uuid,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'revision_number']]);
    }

    public function test_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'project_uuid' => $project->uuid,
        ]);
        $response = $this->getJson("/api/v1/projects/{$project->uuid}/proofing/{$proofing->uuid}");
        $response->assertStatus(401);
    }
}

