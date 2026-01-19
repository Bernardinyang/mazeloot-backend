<?php

namespace Tests\Unit\Database;

use App\Database\ConnectionRetry;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class ConnectionRetryTest extends TestCase
{
    public function test_retries_on_connection_limit_error(): void
    {
        $attempts = 0;
        $callback = function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                // Create a custom exception that mimics QueryException behavior
                $exception = new class('SQLSTATE[42000] [1203] User already has more than \'max_user_connections\' active connections', '42000') extends QueryException {
                    public function __construct($message, $code) {
                        parent::__construct('mysql', 'SELECT * FROM users', [], new \PDOException($message));
                        $this->message = $message;
                        $this->code = $code;
                    }
                };
                
                throw $exception;
            }
            return 'success';
        };

        $result = ConnectionRetry::execute($callback, 3, 10);

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    public function test_throws_after_max_retries(): void
    {
        $this->expectException(QueryException::class);

        $callback = function () {
            $pdoException = new \PDOException('SQLSTATE[42000] [1203] User already has more than \'max_user_connections\' active connections');
            $pdoException->errorInfo = ['42000', '1203', 'User already has more than \'max_user_connections\' active connections'];
            
            $exception = new QueryException(
                'mysql',
                'SELECT * FROM users',
                [],
                $pdoException
            );
            throw $exception;
        };

        ConnectionRetry::execute($callback, 2, 10);
    }

    public function test_does_not_retry_non_connection_errors(): void
    {
        $this->expectException(QueryException::class);

        $callback = function () {
            $pdoException = new \PDOException('SQLSTATE[42S22]: Column not found: 1054 Unknown column');
            $pdoException->errorInfo = ['42S22', '1054', 'Unknown column'];
            
            $exception = new QueryException(
                'mysql',
                'SELECT * FROM users',
                [],
                $pdoException
            );
            throw $exception;
        };

        ConnectionRetry::execute($callback, 3, 10);
    }

    public function test_does_not_retry_non_database_exceptions(): void
    {
        $this->expectException(\RuntimeException::class);

        $callback = function () {
            throw new \RuntimeException('Some other error');
        };

        ConnectionRetry::execute($callback, 3, 10);
    }

    public function test_succeeds_on_first_attempt_when_no_error(): void
    {
        $attempts = 0;
        $callback = function () use (&$attempts) {
            $attempts++;
            return 'success';
        };

        $result = ConnectionRetry::execute($callback, 3, 10);

        $this->assertEquals('success', $result);
        $this->assertEquals(1, $attempts);
    }
}
