<?php

use App\Jobs\ProcessDuePushSchedulesJob;
use App\Console\Commands\RunDailyLedgerHealthScan;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ProcessDuePushSchedulesJob())->everyMinute();

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::command(RunDailyLedgerHealthScan::class)
    ->dailyAt('16:00')
    ->timezone(config('app.timezone'))
    ->appendOutputTo(storage_path('logs/ledger-health-scheduler-test.log'))
    ->withoutOverlapping()
    ->onOneServer();
