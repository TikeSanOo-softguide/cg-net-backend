<?php

namespace Tests\Unit;

use App\Jobs\SendChatWaitingTelegramNotification;
use Tests\TestCase;

class SendChatWaitingTelegramNotificationTest extends TestCase
{
    public function test_job_uses_the_notifications_queue_consumed_by_horizon(): void
    {
        $job = new SendChatWaitingTelegramNotification(1, 'Test notification.');

        $this->assertSame('notifications', $job->queue);
    }
}
