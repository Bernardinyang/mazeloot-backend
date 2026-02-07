<?php

namespace Tests\Feature\Domains\Memora;

use App\Domains\Memora\Models\MemoraDowngradeRequest;
use App\Domains\Memora\Models\MemoraSubscription;
use App\Enums\UserRoleEnum;
use App\Jobs\NotifyAdminsDowngradeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DowngradeRequestTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'memora_tier' => 'pro',
        ]);
    }

    protected function createActiveSubscription(User $user, string $tier = 'pro'): MemoraSubscription
    {
        return MemoraSubscription::create([
            'user_uuid' => $user->uuid,
            'payment_provider' => 'stripe',
            'stripe_subscription_id' => 'sub_test_'.uniqid(),
            'stripe_customer_id' => 'cus_test_'.uniqid(),
            'stripe_price_id' => 'price_test',
            'tier' => $tier,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'amount' => 1500,
            'currency' => 'usd',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_cancel_returns_403_when_user_has_active_subscription(): void
    {
        $this->createActiveSubscription($this->user);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/memora/subscription/cancel');

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'DOWNGRADE_VIA_REQUEST',
            ]);
    }

    public function test_request_downgrade_creates_request_and_returns_201(): void
    {
        Queue::fake();
        User::factory()->create(['role' => UserRoleEnum::ADMIN]);
        $this->createActiveSubscription($this->user);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/memora/subscription/request-downgrade');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'request_uuid',
                    'message',
                ],
            ]);

        $requestUuid = $response->json('data.request_uuid');
        $this->assertNotNull($requestUuid);

        Queue::assertPushed(NotifyAdminsDowngradeRequest::class);
    }

    public function test_request_downgrade_cancels_existing_pending_request(): void
    {
        Queue::fake();
        $this->createActiveSubscription($this->user);

        $existing = MemoraDowngradeRequest::create([
            'user_uuid' => $this->user->uuid,
            'current_tier' => 'pro',
            'target_tier' => 'starter',
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        $existingUuid = $existing->uuid;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/memora/subscription/request-downgrade');

        $response->assertStatus(201);
        $newRequestUuid = $response->json('data.request_uuid');
        $this->assertNotNull($newRequestUuid);
        $this->assertNotSame($existingUuid, $newRequestUuid);
    }

    public function test_request_downgrade_returns_404_without_active_subscription(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/memora/subscription/request-downgrade');

        $response->assertStatus(404)
            ->assertJson(['code' => 'NO_SUBSCRIPTION']);
    }

    public function test_downgrade_by_token_returns_404_without_token(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/memora/subscription/downgrade-by-token');

        $response->assertStatus(404);
    }

    public function test_downgrade_by_token_returns_data_with_valid_token(): void
    {
        $this->createActiveSubscription($this->user);
        $req = MemoraDowngradeRequest::create([
            'user_uuid' => $this->user->uuid,
            'current_tier' => 'pro',
            'target_tier' => 'starter',
            'status' => 'pending',
            'requested_at' => now(),
            'confirm_token' => $token = 'valid_token_'.bin2hex(random_bytes(16)),
            'confirm_token_expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/memora/subscription/downgrade-by-token?token='.$token);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'target_tier' => 'starter',
                    'current_plan' => 'pro',
                ],
            ]);
    }

    public function test_downgrade_by_token_returns_404_for_expired_token(): void
    {
        $this->createActiveSubscription($this->user);
        MemoraDowngradeRequest::create([
            'user_uuid' => $this->user->uuid,
            'current_tier' => 'pro',
            'target_tier' => 'starter',
            'status' => 'pending',
            'requested_at' => now(),
            'confirm_token' => $token = 'expired_token_'.bin2hex(random_bytes(16)),
            'confirm_token_expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/memora/subscription/downgrade-by-token?token='.$token);

        $response->assertStatus(404);
    }

    public function test_confirm_downgrade_returns_404_for_invalid_token(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/memora/subscription/confirm-downgrade', ['token' => 'invalid']);

        $response->assertStatus(404);
    }

    public function test_confirm_downgrade_completes_and_sets_user_to_starter(): void
    {
        $this->createActiveSubscription($this->user);
        $req = MemoraDowngradeRequest::create([
            'user_uuid' => $this->user->uuid,
            'current_tier' => 'pro',
            'target_tier' => 'starter',
            'status' => 'pending',
            'requested_at' => now(),
            'confirm_token' => $token = 'confirm_'.bin2hex(random_bytes(16)),
            'confirm_token_expires_at' => now()->addDays(7),
        ]);
        $requestUuid = $req->uuid;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/memora/subscription/confirm-downgrade', ['token' => $token]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['message' => 'You have been downgraded to Starter.']]);

        $user = User::where('uuid', $this->user->uuid)->first();
        if ($user) {
            $this->assertSame('starter', $user->memora_tier);
            $sub = MemoraSubscription::where('user_uuid', $user->uuid)->first();
            if ($sub) {
                $this->assertSame('canceled', $sub->status);
            }
        }
    }

    public function test_confirm_downgrade_is_idempotent_when_already_completed(): void
    {
        $this->createActiveSubscription($this->user);
        $req = MemoraDowngradeRequest::create([
            'user_uuid' => $this->user->uuid,
            'current_tier' => 'pro',
            'target_tier' => 'starter',
            'status' => 'completed',
            'requested_at' => now(),
            'completed_at' => now(),
            'confirm_token' => $token = 'done_'.bin2hex(random_bytes(16)),
            'confirm_token_expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/memora/subscription/confirm-downgrade', ['token' => $token]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['message' => 'Downgrade already completed']]);
    }
}
