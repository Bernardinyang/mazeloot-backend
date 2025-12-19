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

        $response = $this->postJson('/api/uploads', [
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

        $response = $this->postJson('/api/uploads', [
            'files' => $files,
            'purpose' => 'test',
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/api/uploads', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_upload_validates_file_requirement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/uploads', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_validates_file_size(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        config(['upload.max_size' => 1024]); // 1KB limit

        $file = UploadedFile::fake()->create('large.jpg', 5000); // 5KB

        $response = $this->postJson('/api/uploads', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
