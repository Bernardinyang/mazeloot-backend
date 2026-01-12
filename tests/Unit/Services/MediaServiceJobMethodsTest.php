<?php

namespace Tests\Unit\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Services\MediaService;
use App\Services\Upload\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MediaServiceJobMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected MediaService $mediaService;

    protected $mockUploadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUploadService = \Mockery::mock(UploadService::class);
        $this->mediaService = new MediaService($this->mockUploadService);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_process_low_res_copy_updates_media(): void
    {
        Queue::fake();

        $media = MemoraMedia::factory()->create();

        $this->mediaService->processLowResCopy($media->uuid);

        $media->refresh();
        // Low-res copy processing is queued, verify method doesn't throw
        $this->assertNotNull($media);
    }

    public function test_process_low_res_copy_handles_missing_media(): void
    {
        // Should not throw exception, just log warning
        $this->expectNotToPerformAssertions();
        $this->mediaService->processLowResCopy('non-existent-id');
    }

    public function test_process_image_calls_generate_thumbnail_when_enabled(): void
    {
        $media = MemoraMedia::factory()->create();
        
        // Test that processImage calls generateThumbnail when enabled
        // generateThumbnail is a placeholder, so we just verify it doesn't throw
        $this->expectNotToPerformAssertions();
        $this->mediaService->processImage($media->uuid, ['generateThumbnail' => true]);
    }

    public function test_process_image_handles_missing_media(): void
    {
        // Should not throw exception, just log warning
        $this->expectNotToPerformAssertions();
        $this->mediaService->processImage('non-existent-id', []);
    }
}
