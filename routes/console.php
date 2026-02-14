<?php

use App\Jobs\WorkerHeartbeatJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule auto-deletion of expired selections to run daily at 2 AM
Schedule::command('selections:auto-delete')
    ->dailyAt('02:00')
    ->name('auto-delete-selections')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule auto-deletion of expired raw files to run daily at 2:30 AM
Schedule::command('raw-files:auto-delete')
    ->dailyAt('02:30')
    ->name('auto-delete-raw-files')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule cleanup of old activity logs to run daily at 3:00 AM (keep last 90 days)
Schedule::command('activity-logs:cleanup --days=90')
    ->dailyAt('03:00')
    ->name('cleanup-activity-logs')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () {
    Cache::put('admin.scheduler_last_run', now()->toIso8601String(), 600);
})->everyMinute()->name('scheduler-heartbeat');

Schedule::job(new WorkerHeartbeatJob())
    ->everyMinute()
    ->name('worker-heartbeat')
    ->withoutOverlapping()
    ->onOneServer();
