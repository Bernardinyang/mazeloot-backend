<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CacheSanctumTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Cache::store('file')->flush();
    }

    public function test_caches_valid_token_after_first_request(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Clear query log
        DB::enableQueryLog();
        DB::flushQueryLog();

        // First request - should query database
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');

        $queries1 = count(DB::getQueryLog());
        $this->assertGreaterThan(0, $queries1);
        $response1->assertStatus(200);

        // Clear query log again
        DB::flushQueryLog();

        // Second request - should use cache (fewer queries)
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');

        $queries2 = count(DB::getQueryLog());
        $response2->assertStatus(200);
        $this->assertEquals($response1->json('data.uuid'), $response2->json('data.uuid'));

        // Verify cache was used (should have fewer queries on second request)
        // Note: Some queries may still occur for other operations, but token lookup should be cached
        $this->assertLessThan($queries1, $queries2 + 1); // Allow for some variance
    }

    public function test_returns_401_for_cached_invalid_token(): void
    {
        $invalidToken = 'invalid-token-hash';
        $tokenHash = hash('sha256', $invalidToken);
        $cacheKey = "sanctum_token:{$tokenHash}";

        // Cache invalid token using file store (matches middleware)
        Cache::store('file')->put($cacheKey, false, 5);

        // Request with invalid token should return 401 immediately
        $response = $this->withHeader('Authorization', "Bearer {$invalidToken}")
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
        $this->assertEquals('UNAUTHENTICATED', $response->json('code'));
    }

    public function test_caches_user_object_not_uuid(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $tokenHash = hash('sha256', $token);
        $cacheKey = "sanctum_token:{$tokenHash}";

        // Make first request to populate cache
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');

        // Verify cache contains serialized user object, not just UUID
        // Use file store to match middleware
        $cached = Cache::store('file')->get($cacheKey);
        $this->assertNotNull($cached);
        $this->assertNotEquals($user->uuid, $cached); // Should be serialized object, not UUID

        // Unserialize and verify it's a User object
        $unserialized = unserialize($cached);
        $this->assertInstanceOf(User::class, $unserialized);
        $this->assertEquals($user->uuid, $unserialized->uuid);
    }

    public function test_multiple_requests_use_cached_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Make first request
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');
        $response1->assertStatus(200);

        // Make 5 more requests - all should use cache
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/v1/auth/user');
            $response->assertStatus(200);
            $this->assertEquals($response1->json('data.uuid'), $response->json('data.uuid'));
        }

        // Verify cache still exists (using file store to match middleware)
        $tokenHash = hash('sha256', $token);
        $cacheKey = "sanctum_token:{$tokenHash}";
        $this->assertNotNull(Cache::store('file')->get($cacheKey));
    }
}
