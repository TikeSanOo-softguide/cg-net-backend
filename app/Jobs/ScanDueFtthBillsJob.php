<?php

namespace App\Jobs;

use App\Services\Notification\DueFtthBillNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanDueFtthBillsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return 'scan-due-ftth-bills';
    }

    public function handle(DueFtthBillNotificationService $service): void
    {
        $service->dispatchDueBills();
    }
}
