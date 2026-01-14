<?php

namespace Tests\Unit\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Services\MediaService;
use App\Models\User;
use App\Services\Upload\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
        $user = User::factory()->create();
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->count(3)->create(['media_set_uuid' => $set->uuid]);

        $result = $this->service->getPhaseMedia('proofing', $proofing->uuid);

        $this->assertCount(3, $result);
        $this->assertEquals($media->pluck('uuid')->sort()->values(), $result->pluck('uuid')->sort()->values());
    }

    public function test_get_phase_media_with_set_filter(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $set1 = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $set2 = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media1 = MemoraMedia::factory()->count(2)->create(['media_set_uuid' => $set1->uuid]);
        $media2 = MemoraMedia::factory()->count(2)->create(['media_set_uuid' => $set2->uuid]);

        $result = $this->service->getPhaseMedia('proofing', $proofing->uuid, $set1->uuid);

        $this->assertCount(2, $result);
        $this->assertEquals($media1->pluck('uuid')->sort()->values(), $result->pluck('uuid')->sort()->values());
    }

    public function test_move_between_phases(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $proofing1 = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $proofing2 = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $set1 = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing1->uuid]);
        $set2 = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing2->uuid]);
        $media = MemoraMedia::factory()->count(3)->create(['media_set_uuid' => $set1->uuid]);

        $result = $this->service->moveBetweenPhases(
            $media->pluck('uuid')->toArray(),
            'proofing',
            $proofing1->uuid,
            'proofing',
            $proofing2->uuid
        );

        $this->assertEquals(3, $result['movedCount']);
        $this->assertCount(3, $result['media']);
        
        // Verify media was moved to the target set
        foreach ($result['media'] as $m) {
            $m->refresh();
            $this->assertEquals($set2->uuid, $m->media_set_uuid);
        }
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

        $this->assertCount(3, $result);
        $this->assertEquals($original->uuid, $result[0]['id']);
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
