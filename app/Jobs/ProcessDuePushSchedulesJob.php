<?php

namespace App\Jobs;

use App\Services\Notification\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDuePushSchedulesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 55;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return 'process-due-push-schedules';
    }

    public function handle(PushNotificationService $service): void
    {
        $service->processDue();
    }
}
