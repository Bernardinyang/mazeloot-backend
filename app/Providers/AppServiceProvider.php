<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
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
    }
}
