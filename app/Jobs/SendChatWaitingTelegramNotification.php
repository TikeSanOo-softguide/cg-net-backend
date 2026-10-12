<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendChatWaitingTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $conversationId,
        public readonly string $message,
    ) {}

    public function handle(TelegramService $telegram): void
    {
        $telegram->broadbandApplicaiton($this->message);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Chat waiting Telegram notification failed.', [
            'conversation_id' => $this->conversationId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
