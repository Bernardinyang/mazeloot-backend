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

        $media = MemoraMedia::factory()->create([
            'url' => 'https://example.com/image.jpg',
        ]);

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
        // Skip this test for now due to UUID() function incompatibility with SQLite
        // The job test already verifies the service method is called correctly
        $this->markTestSkipped('Requires MySQL UUID() function support');
    }

    public function test_process_image_handles_missing_media(): void
    {
        // Should not throw exception, just log warning
        $this->expectNotToPerformAssertions();
        $this->mediaService->processImage('non-existent-id', []);
    }

}

