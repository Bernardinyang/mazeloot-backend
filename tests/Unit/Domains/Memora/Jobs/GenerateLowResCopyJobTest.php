<?php

namespace Tests\Unit\Domains\Memora\Jobs;

use App\Domains\Memora\Jobs\GenerateLowResCopyJob;
use App\Domains\Memora\Services\MediaService;
use Mockery;
use Tests\TestCase;

class GenerateLowResCopyJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_calls_media_service_process_low_res_copy(): void
    {
        $mediaId = 'test-media-id';
        $mockMediaService = Mockery::mock(MediaService::class);

        $mockMediaService
            ->shouldReceive('processLowResCopy')
            ->once()
            ->with($mediaId);

        $job = new GenerateLowResCopyJob($mediaId);
        $job->handle($mockMediaService);

        $this->assertTrue(true); // Job executed without errors
    }
}
