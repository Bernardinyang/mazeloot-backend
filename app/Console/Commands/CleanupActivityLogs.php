<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class CleanupActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity-logs:cleanup {--days=90 : Number of days to keep logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old activity logs (default: keep last 90 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up activity logs older than {$days} days (before {$cutoffDate->toDateString()})...");

        $deleted = ActivityLog::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$deleted} activity log entries.");

        return Command::SUCCESS;
    }
}
