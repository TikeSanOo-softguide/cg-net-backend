<?php

namespace Tests\Unit;

use App\Services\Telegram\TelegramService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_notification_is_skipped_when_chat_id_is_not_configured(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => null,
        ]);
        Http::fake();
        Log::shouldReceive('info')->once();

        $response = app(TelegramService::class)->broadbandApplicaiton('Request submitted.');

        $this->assertNull($response);
        Http::assertNothingSent();
    }

    public function test_telegram_api_failure_does_not_throw_or_fail_calling_flow(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => false], 400),
        ]);
        Log::shouldReceive('warning')->once();

        $response = app(TelegramService::class)->broadbandApplicaiton('Request submitted.');

        $this->assertNull($response);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_does_not_throw_or_fail_calling_flow(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);
        Http::fake([
            'https://api.telegram.org/*' => function () {
                throw new ConnectionException('Telegram is unreachable.');
            },
        ]);
        Log::shouldReceive('warning')->once();

        $response = app(TelegramService::class)->broadbandApplicaiton('Request submitted.');

        $this->assertNull($response);
        Http::assertNothingSent();
    }
}
