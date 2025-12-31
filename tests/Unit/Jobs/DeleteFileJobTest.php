<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeleteFileJob;
use App\Services\Upload\UploadService;
use Mockery;
use Tests\TestCase;

class DeleteFileJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_calls_upload_service_delete_files(): void
    {
        $filePath = 'path/to/file.jpg';
        $additionalPaths = ['path/to/thumbnail.jpg', 'path/to/lowres.jpg'];

        $mockUploadService = Mockery::mock(UploadService::class);

        $mockUploadService
            ->shouldReceive('deleteFiles')
            ->once()
            ->with($filePath, $additionalPaths);

        $job = new DeleteFileJob($filePath, $additionalPaths);
        $job->handle($mockUploadService);

        $this->assertTrue(true); // Job executed without errors
    }

    public function test_job_handles_null_additional_paths(): void
    {
        $filePath = 'path/to/file.jpg';
        $mockUploadService = Mockery::mock(UploadService::class);

        $mockUploadService
            ->shouldReceive('deleteFiles')
            ->once()
            ->with($filePath, null);

        $job = new DeleteFileJob($filePath);
        $job->handle($mockUploadService);

        $this->assertTrue(true); // Job executed without errors
    }
}
