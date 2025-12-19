<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Upload\UploadService;
use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Quotas\QuotaService;
use Mockery;

class UploadServiceDeleteFilesTest extends TestCase
{
    protected UploadService $uploadService;
    protected $mockProvider;
    protected $mockQuotaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProvider = Mockery::mock(UploadProviderInterface::class);
        $this->mockQuotaService = Mockery::mock(QuotaService::class);

        $this->uploadService = new UploadService(
            $this->mockProvider,
            $this->mockQuotaService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delete_files_calls_provider_delete(): void
    {
        $filePath = 'path/to/file.jpg';

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with($filePath)
            ->andReturn(true);

        $this->uploadService->deleteFiles($filePath);

        $this->assertTrue(true); // Method executed without errors
    }

    public function test_delete_files_handles_additional_paths(): void
    {
        $filePath = 'path/to/file.jpg';
        $additionalPaths = ['path/to/thumbnail.jpg', 'path/to/lowres.jpg'];

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with($filePath)
            ->andReturn(true);

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with('path/to/thumbnail.jpg')
            ->andReturn(true);

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with('path/to/lowres.jpg')
            ->andReturn(true);

        $this->uploadService->deleteFiles($filePath, $additionalPaths);

        $this->assertTrue(true); // Method executed without errors
    }

    public function test_delete_files_handles_failed_deletion_gracefully(): void
    {
        $filePath = 'path/to/file.jpg';

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with($filePath)
            ->andReturn(false);

        // Should not throw exception
        $this->expectNotToPerformAssertions();
        $this->uploadService->deleteFiles($filePath);
    }

    public function test_delete_files_continues_on_additional_path_failure(): void
    {
        $filePath = 'path/to/file.jpg';
        $additionalPaths = ['path/to/thumbnail.jpg', 'path/to/lowres.jpg'];

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with($filePath)
            ->andReturn(true);

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with('path/to/thumbnail.jpg')
            ->andThrow(new \Exception('Delete failed'));

        $this->mockProvider
            ->shouldReceive('delete')
            ->once()
            ->with('path/to/lowres.jpg')
            ->andReturn(true);

        // Should not throw exception, continues with other files
        $this->expectNotToPerformAssertions();
        $this->uploadService->deleteFiles($filePath, $additionalPaths);
    }
}

