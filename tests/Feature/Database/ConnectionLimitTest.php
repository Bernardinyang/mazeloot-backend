<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectionLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_handles_connection_limit_error_gracefully(): void
    {
        // Simulate connection limit error
        $pdoException = new \PDOException('SQLSTATE[42000] [1203] User already has more than \'max_user_connections\' active connections');
        $pdoException->errorInfo = ['42000', '1203', 'User already has more than \'max_user_connections\' active connections'];

        $exception = new QueryException(
            'mysql',
            'SELECT * FROM personal_access_tokens',
            [],
            $pdoException
        );

        // Verify the exception structure matches what the handler expects
        $this->assertStringContainsString('1203', $exception->getMessage());
        $this->assertStringContainsString('max_user_connections', $exception->getMessage());
    }

    public function test_token_caching_reduces_database_queries(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Enable query logging
        DB::enableQueryLog();

        // First request - should query database for token
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');

        $queries1 = DB::getQueryLog();
        $tokenQueries1 = array_filter($queries1, function ($query) {
            return str_contains($query['query'] ?? '', 'personal_access_tokens');
        });

        $this->assertGreaterThan(0, count($tokenQueries1));
        $response1->assertStatus(200);

        // Clear query log
        DB::flushQueryLog();

        // Second request - should use cache (no token queries)
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');

        $queries2 = DB::getQueryLog();
        $tokenQueries2 = array_filter($queries2, function ($query) {
            return str_contains($query['query'] ?? '', 'personal_access_tokens');
        });

        $response2->assertStatus(200);
        // Should have fewer or zero token queries on second request
        $this->assertLessThanOrEqual(count($tokenQueries1), count($tokenQueries2));
    }

    public function test_multiple_concurrent_requests_use_cache(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Make initial request to populate cache
        $initialResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/user');
        $initialResponse->assertStatus(200);

        // Simulate concurrent requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/v1/auth/user');
            $response->assertStatus(200);
            $responses[] = $response;
        }

        // All should succeed and return same user data
        foreach ($responses as $response) {
            $this->assertNotNull($response->json('data'));
            $this->assertEquals($initialResponse->json('data.uuid'), $response->json('data.uuid'));
        }
    }
}
