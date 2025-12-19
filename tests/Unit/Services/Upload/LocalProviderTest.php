<?php

namespace Tests\Unit\Services\Upload;

use Tests\TestCase;
use App\Services\Upload\Providers\LocalProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LocalProviderTest extends TestCase
{
    protected LocalProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['upload.providers.local.disk' => 'local']);

        $this->provider = new LocalProvider();
    }

    public function test_upload_file_successfully(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $result = $this->provider->upload($file, []);

        $this->assertEquals('local', $result->provider);
        $this->assertNotNull($result->url);
        $this->assertEquals('image/jpeg', $result->mimeType);
        $this->assertEquals('test.jpg', $result->originalFilename);
        $this->assertNotNull($result->path);

        Storage::disk('local')->assertExists($result->path);
    }

    public function test_delete_file_successfully(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $result = $this->provider->upload($file, []);

        $deleted = $this->provider->delete($result->path);

        $this->assertTrue($deleted);
        Storage::disk('local')->assertMissing($result->path);
    }

    public function test_get_public_url(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $result = $this->provider->upload($file, []);

        $url = $this->provider->getPublicUrl($result->path);

        $this->assertIsString($url);
        $this->assertStringContainsString($result->path, $url);
    }
}
