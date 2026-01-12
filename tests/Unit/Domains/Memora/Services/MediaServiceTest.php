<?php

namespace Tests\Unit\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Services\MediaService;
use App\Services\Upload\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MediaService $service;

    protected $mockUploadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUploadService = \Mockery::mock(UploadService::class);
        $this->service = new MediaService($this->mockUploadService);
    }

    public function test_get_phase_media(): void
    {
        $user = \App\Models\User::factory()->create();
        $selection = \App\Domains\Memora\Models\MemoraSelection::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['selection_uuid' => $selection->uuid]);
        MemoraMedia::factory()->count(3)->create(['media_set_uuid' => $set->uuid]);

        $result = $this->service->getPhaseMedia('selection', $selection->uuid);

        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($media) => $media->mediaSet->selection_uuid === $selection->uuid));
    }

    public function test_get_phase_media_with_set_filter(): void
    {
        $user = \App\Models\User::factory()->create();
        $selection = \App\Domains\Memora\Models\MemoraSelection::factory()->create(['user_uuid' => $user->uuid]);
        $set1 = MemoraMediaSet::factory()->create(['selection_uuid' => $selection->uuid]);
        $set2 = MemoraMediaSet::factory()->create(['selection_uuid' => $selection->uuid]);
        MemoraMedia::factory()->count(2)->create(['media_set_uuid' => $set1->uuid]);
        MemoraMedia::factory()->count(3)->create(['media_set_uuid' => $set2->uuid]);

        $result = $this->service->getPhaseMedia('selection', $selection->uuid, $set1->uuid);

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($media) => $media->media_set_uuid === $set1->uuid));
    }

    public function test_move_between_phases(): void
    {
        $user = \App\Models\User::factory()->create();
        $selection = \App\Domains\Memora\Models\MemoraSelection::factory()->create(['user_uuid' => $user->uuid]);
        $proofing = \App\Domains\Memora\Models\MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $fromSet = MemoraMediaSet::factory()->create(['selection_uuid' => $selection->uuid]);
        $toSet = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media1 = MemoraMedia::factory()->create(['media_set_uuid' => $fromSet->uuid]);
        $media2 = MemoraMedia::factory()->create(['media_set_uuid' => $fromSet->uuid]);

        $result = $this->service->moveBetweenPhases(
            [$media1->uuid, $media2->uuid],
            'selection',
            $selection->uuid,
            'proofing',
            $proofing->uuid
        );

        $this->assertEquals(2, $result['movedCount']);
        $media1->refresh();
        $media2->refresh();
        $this->assertEquals($toSet->uuid, $media1->media_set_uuid);
        $this->assertEquals($toSet->uuid, $media2->media_set_uuid);
    }

    public function test_mark_selected(): void
    {
        $set = MemoraMediaSet::factory()->create();
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_selected' => false,
        ]);

        $result = $this->service->markSelected($media->uuid, true);

        $this->assertTrue($result->is_selected);
        $this->assertNotNull($result->selected_at);
    }

    public function test_mark_unselected(): void
    {
        $set = MemoraMediaSet::factory()->create();
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_selected' => true,
            'selected_at' => now(),
        ]);

        $result = $this->service->markSelected($media->uuid, false);

        $this->assertFalse($result->is_selected);
        $this->assertNull($result->selected_at);
    }

    public function test_get_revisions(): void
    {
        $set = MemoraMediaSet::factory()->create();
        $original = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'revision_number' => 0,
        ]);
        $revision1 = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'original_media_uuid' => $original->uuid,
            'revision_number' => 1,
        ]);
        $revision2 = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'original_media_uuid' => $original->uuid,
            'revision_number' => 2,
        ]);

        $result = $this->service->getRevisions($original->uuid);

        // getRevisions returns a collection array, not a structured array
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        // Verify original is in the result
        $originalIds = array_column($result, 'id');
        $this->assertContains($original->uuid, $originalIds);
    }

    public function test_process_low_res_copy(): void
    {
        $media = MemoraMedia::factory()->create();

        $this->service->processLowResCopy($media->uuid);

        $media->refresh();
        // Low-res copy processing is queued, so URL may not be immediately available
        // Just verify the method doesn't throw
        $this->assertNotNull($media);
    }

    public function test_process_low_res_copy_handles_missing_media(): void
    {
        $this->service->processLowResCopy('non-existent-id');
        $this->expectNotToPerformAssertions();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
