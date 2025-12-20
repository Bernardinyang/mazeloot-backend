<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
