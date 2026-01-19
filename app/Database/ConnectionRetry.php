<?php

namespace App\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ConnectionRetry
{
    /**
     * Execute a callback with retry logic for connection errors.
     *
     * @param  callable  $callback
     * @param  int  $maxRetries
     * @param  int  $initialDelay
     * @return mixed
     *
     * @throws \Exception
     */
    public static function execute(callable $callback, int $maxRetries = 3, int $initialDelay = 100)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (QueryException $e) {
                $lastException = $e;

                // Check if it's a connection limit error (MySQL error 1203)
                if (self::isConnectionLimitError($e)) {
                    $attempt++;

                    if ($attempt >= $maxRetries) {
                        Log::warning('Database connection limit reached after retries', [
                            'attempts' => $attempt,
                            'error' => $e->getMessage(),
                        ]);

                        throw $e;
                    }

                    // Exponential backoff: 100ms, 200ms, 400ms
                    $delay = $initialDelay * (2 ** ($attempt - 1));
                    usleep($delay * 1000); // Convert to microseconds

                    Log::info('Retrying database connection', [
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                    ]);

                    continue;
                }

                // Not a connection limit error, rethrow immediately
                throw $e;
            } catch (\Exception $e) {
                // Not a database error, rethrow immediately
                throw $e;
            }
        }

        throw $lastException;
    }

    /**
     * Check if the exception is a connection limit error.
     *
     * @param  QueryException  $e
     * @return bool
     */
    protected static function isConnectionLimitError(QueryException $e): bool
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        // MySQL error 1203: User already has more than 'max_user_connections' active connections
        return $errorCode === '42000' && (
            str_contains($errorMessage, '1203') ||
            str_contains($errorMessage, 'max_user_connections') ||
            str_contains($errorMessage, 'User already has more than')
        );
    }
}
