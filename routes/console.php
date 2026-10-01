<?php

use App\Console\Commands\RunDailyLedgerHealthScan;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(RunDailyLedgerHealthScan::class)
    ->dailyAt('16:00')
    ->timezone(config('app.timezone'))
    ->appendOutputTo(storage_path('logs/ledger-health-scheduler-test.log'))
    ->withoutOverlapping()
    ->onOneServer();
