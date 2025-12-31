<?php

namespace Tests\Unit\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraClosureRequest;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Services\ClosureRequestService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClosureRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ClosureRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClosureRequestService();
    }

    public function test_create_closure_request(): void
    {
        $user = User::factory()->create();
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_completed' => false,
        ]);

        Notification::fake();

        $request = $this->service->create(
            $proofing->uuid,
            $media->uuid,
            ['Fix colors', 'Adjust brightness'],
            $user->uuid
        );

        $this->assertEquals('pending', $request->status);
        $this->assertEquals($media->uuid, $request->media_uuid);
        $this->assertCount(2, $request->todos);
    }

    public function test_create_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $owner->uuid]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create(['media_set_uuid' => $set->uuid]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized');
        $this->service->create($proofing->uuid, $media->uuid, [], $otherUser->uuid);
    }

    public function test_create_blocks_completed_media(): void
    {
        $user = User::factory()->create();
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_completed' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('approved media');
        $this->service->create($proofing->uuid, $media->uuid, [], $user->uuid);
    }

    public function test_create_blocks_duplicate_pending(): void
    {
        $user = User::factory()->create();
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create(['media_set_uuid' => $set->uuid]);
        MemoraClosureRequest::factory()->create([
            'media_uuid' => $media->uuid,
            'status' => 'pending',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already pending');
        $this->service->create($proofing->uuid, $media->uuid, [], $user->uuid);
    }

    public function test_approve_closure_request(): void
    {
        $user = User::factory()->create();
        $proofing = MemoraProofing::factory()->create([
            'user_uuid' => $user->uuid,
            'primary_email' => 'test@example.com',
        ]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $set->uuid,
            'is_ready_for_revision' => false,
        ]);
        $request = MemoraClosureRequest::factory()->create([
            'proofing_uuid' => $proofing->uuid,
            'media_uuid' => $media->uuid,
            'status' => 'pending',
        ]);

        Notification::fake();

        $approved = $this->service->approve($request->token, 'test@example.com');

        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
        $media->refresh();
        $this->assertTrue($media->is_ready_for_revision);
    }

    public function test_reject_closure_request(): void
    {
        $user = User::factory()->create();
        $proofing = MemoraProofing::factory()->create(['user_uuid' => $user->uuid]);
        $set = MemoraMediaSet::factory()->create(['proof_uuid' => $proofing->uuid]);
        $media = MemoraMedia::factory()->create(['media_set_uuid' => $set->uuid]);
        $request = MemoraClosureRequest::factory()->create([
            'proofing_uuid' => $proofing->uuid,
            'media_uuid' => $media->uuid,
            'status' => 'pending',
        ]);

        Notification::fake();

        $rejected = $this->service->reject($request->token, 'test@example.com', 'Not good enough');

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Not good enough', $rejected->rejection_reason);
    }
}

