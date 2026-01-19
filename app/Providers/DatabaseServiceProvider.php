<?php

namespace App\Providers;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\ConnectionFailed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Log connection errors
        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event) {
            Log::error('Database connection failed', [
                'connection' => $event->connectionName,
                'error' => $event->exception->getMessage(),
            ]);
        });

        // Track connection usage (only in non-production for performance)
        if (config('app.debug')) {
            Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
                $connectionCount = $this->getActiveConnectionCount($event->connectionName);

                // Log warning if approaching connection limit (assuming 10 is typical for shared hosting)
                if ($connectionCount >= 8) {
                    Log::warning('Approaching database connection limit', [
                        'connection' => $event->connectionName,
                        'active_connections' => $connectionCount,
                    ]);
                }
            });

            // Log slow queries
            Event::listen(QueryExecuted::class, function (QueryExecuted $event) {
                if ($event->time > 1000) { // Log queries taking more than 1 second
                    Log::warning('Slow database query detected', [
                        'connection' => $event->connectionName,
                        'time_ms' => $event->time,
                        'sql' => $event->sql,
                    ]);
                }
            });
        }
    }

    /**
     * Get active connection count for a connection.
     */
    protected function getActiveConnectionCount(string $connectionName): int
    {
        try {
            $result = DB::connection($connectionName)->select('SHOW PROCESSLIST');

            return count($result);
        } catch (\Exception $e) {
            // If we can't query, return 0
            return 0;
        }
    }
}
