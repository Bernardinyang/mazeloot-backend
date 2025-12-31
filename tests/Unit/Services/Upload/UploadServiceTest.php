<?php

namespace Tests\Unit\Services\Upload;

use App\Services\Quotas\QuotaService;
use App\Services\Upload\Contracts\UploadProviderInterface;
use App\Services\Upload\DTOs\UploadResult;
use App\Services\Upload\Exceptions\UploadException;
use App\Services\Upload\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class UploadServiceTest extends TestCase
{
    protected UploadService $uploadService;

    protected $mockProvider;

    protected $mockQuotaService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->mockProvider = Mockery::mock(UploadProviderInterface::class);
        $this->mockQuotaService = Mockery::mock(QuotaService::class);

        $this->uploadService = new UploadService(
            $this->mockProvider,
            $this->mockQuotaService
        );
    }

    public function test_upload_single_file_successfully(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $fileSize = $file->getSize();

        $uploadResult = new UploadResult(
            url: 'https://example.com/file.jpg',
            provider: 'local',
            path: 'uploads/test.jpg',
            mimeType: 'image/jpeg',
            size: $fileSize,
            checksum: 'abc123',
            originalFilename: 'test.jpg'
        );

        $this->mockQuotaService
            ->shouldReceive('checkUploadQuota')
            ->once()
            ->with(Mockery::type('int'), 'memora', 1);

        $this->mockProvider
            ->shouldReceive('upload')
            ->once()
            ->with(Mockery::on(fn ($f) => $f instanceof UploadedFile), ['purpose' => 'test', 'domain' => 'memora', 'userId' => 1])
            ->andReturn($uploadResult);

        $result = $this->uploadService->upload($file, [
            'purpose' => 'test',
            'domain' => 'memora',
            'userId' => 1,
        ]);

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertEquals('https://example.com/file.jpg', $result->url);
    }

    public function test_upload_throws_exception_when_file_too_large(): void
    {
        $file = UploadedFile::fake()->create('test.jpg', 20000); // 20MB

        $this->mockQuotaService
            ->shouldReceive('checkUploadQuota')
            ->never();

        $this->mockProvider
            ->shouldReceive('upload')
            ->never();

        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('File size exceeds');

        $this->uploadService->upload($file, [
            'maxSize' => 10485760, // 10MB limit
            'domain' => 'memora',
        ]);
    }

    public function test_upload_multiple_files(): void
    {
        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.jpg'),
        ];

        $uploadResult = new UploadResult(
            url: 'https://example.com/file.jpg',
            provider: 'local',
            path: 'uploads/test.jpg',
            mimeType: 'image/jpeg',
            size: 1024,
            checksum: 'abc123',
            originalFilename: 'test.jpg'
        );

        $this->mockQuotaService
            ->shouldReceive('checkUploadQuota')
            ->times(2);

        $this->mockProvider
            ->shouldReceive('upload')
            ->times(2)
            ->andReturn($uploadResult);

        $results = $this->uploadService->uploadMultiple($files, ['domain' => 'memora']);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(UploadResult::class, $results[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
