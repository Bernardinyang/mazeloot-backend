<?php

namespace Tests\Unit\Domains\Memora\Jobs;

use Tests\TestCase;
use App\Domains\Memora\Jobs\ProcessImageJob;
use App\Domains\Memora\Services\MediaService;
use Mockery;

class ProcessImageJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_calls_media_service_process_image(): void
    {
        $mediaId = 'test-media-id';
        $options = [
            'generateThumbnail' => true,
            'generateLowRes' => true,
            'extractExif' => false,
        ];

        $mockMediaService = Mockery::mock(MediaService::class);

        $mockMediaService
            ->shouldReceive('processImage')
            ->once()
            ->with($mediaId, $options);

        $job = new ProcessImageJob($mediaId, $options);
        $job->handle($mockMediaService);

        $this->assertTrue(true); // Job executed without errors
    }

    public function test_job_uses_default_options(): void
    {
        $mediaId = 'test-media-id';
        $mockMediaService = Mockery::mock(MediaService::class);

        $mockMediaService
            ->shouldReceive('processImage')
            ->once()
            ->with($mediaId, []);

        $job = new ProcessImageJob($mediaId);
        $job->handle($mockMediaService);

        $this->assertTrue(true); // Job executed without errors
    }
}

