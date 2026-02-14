<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Session\DatabaseSessionHandler;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register UserPolicy
        Gate::policy(User::class, UserPolicy::class);

        // Load broadcast channels
        require base_path('routes/channels.php');

        // Override database session handler to use user_uuid instead of user_id
        Session::extend('database', function ($app) {
            $connection = $app['db']->connection($app['config']['session.connection']);
            $table = $app['config']['session.table'];
            $minutes = $app['config']['session.lifetime'];

            return new DatabaseSessionHandler($connection, $table, $minutes, $app);
        });

        // Update worker heartbeat on each queue loop so queue:work is detected without relying on scheduler
        Queue::looping(function () {
            if (config('queue.default') !== 'sync') {
                Cache::put('admin.worker_heartbeat', now()->toIso8601String(), 120);
            }
        });

        // Keep last 50 processed job names for admin visibility (Laravel does not store this by default)
        Queue::after(function (JobProcessed $event) {
            if (config('queue.default') === 'sync') {
                return;
            }
            try {
                $payload = $event->job->payload();
                $displayName = $payload['displayName'] ?? $payload['data']['commandName'] ?? null;
                if (! $displayName && isset($payload['data']['command'])) {
                    $cmd = @unserialize($payload['data']['command']);
                    $displayName = $cmd ? class_basename($cmd) : null;
                }
                $displayName = $displayName ?? 'Unknown';
                $entry = ['name' => $displayName, 'processed_at' => now()->toIso8601String()];
                $list = Cache::get('admin.recent_processed_jobs', []);
                array_unshift($list, $entry);
                $list = array_slice($list, 0, 50);
                Cache::put('admin.recent_processed_jobs', $list, 3600);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        // Close idle database connections after each request to prevent connection exhaustion
        $this->app->terminating(function () {
            try {
                // Disconnect all database connections to free them up
                foreach (array_keys(config('database.connections')) as $connection) {
                    DB::connection($connection)->disconnect();
                }
            } catch (\Exception $e) {
                // Silently fail if disconnection fails
                // Log only in debug mode
                if (config('app.debug')) {
                    \Illuminate\Support\Facades\Log::debug('Failed to disconnect database connection', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
