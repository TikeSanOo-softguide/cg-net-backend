<?php

namespace Tests\Unit;

use App\Services\Telegram\TelegramService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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

    public function test_chat_waiting_notification_throws_when_telegram_rejects_the_message(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ], 400),
        ]);
        Log::shouldReceive('warning')->once();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Telegram rejected or could not deliver the chat notification.');

        app(TelegramService::class)->sendChatWaitingNotification('New support request.');
    }

    public function test_http_success_with_telegram_ok_false_is_treated_as_failure(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ], 200),
        ]);
        Log::shouldReceive('warning')->once();

        $response = app(TelegramService::class)->broadbandApplicaiton('Request submitted.');

        $this->assertNull($response);
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

    public function test_chat_waiting_notification_throws_when_telegram_connection_fails(): void
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Telegram rejected or could not deliver the chat notification.');

        app(TelegramService::class)->sendChatWaitingNotification('New support request.');
    }
}
