<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearAllCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all application caches (config, route, view, event, application, and bootstrap cache)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing all caches...');

        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        $this->call('event:clear');

        // Clear bootstrap cache files
        $bootstrapCachePath = base_path('bootstrap/cache');
        if (is_dir($bootstrapCachePath)) {
            $files = glob($bootstrapCachePath.'/*.php');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') {
                    unlink($file);
                }
            }
        }

        // Restart queue workers
        $this->call('queue:restart');

        $this->info('All caches cleared successfully!');

        return 0;
    }
}
