<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class UploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['upload.default_provider' => 'local']);
    }

    public function test_user_can_upload_single_file(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
            'purpose' => 'test',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'url',
                    'provider',
                    'path',
                    'mimeType',
                    'size',
                    'originalFilename',
                ],
            ]);
    }

    public function test_user_can_upload_multiple_files(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.jpg'),
        ];

        $response = $this->postJson('/api/v1/uploads', [
            'files' => $files,
            'purpose' => 'test',
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_upload_validates_file_requirement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/uploads', []);

        $response->assertStatus(422);
        // ApiResponse returns error message, not validation errors array
        $this->assertNotNull($response->json('message'));
    }

    public function test_upload_validates_file_size(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Set a very small max size for testing (1KB)
        $originalMaxSize = config('upload.max_size');
        config(['upload.max_size' => 1024]);
        
        // Create a file larger than the limit (5KB)
        $file = UploadedFile::fake()->create('large.jpg', 5000);

        try {
            $response = $this->postJson('/api/v1/uploads', [
                'file' => $file,
            ]);

            // Should fail validation due to file size (400 or 422)
            $this->assertContains($response->status(), [400, 422]);
        } finally {
            // Restore original config
            config(['upload.max_size' => $originalMaxSize]);
        }
    }
}
