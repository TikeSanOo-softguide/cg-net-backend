<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramService
{
    public function broadbandApplicaiton(string $message, ?string $chatId = null): ?Response
    {
        $chatId ??= config('services.telegram.chat_id');
        $botToken = config('services.telegram.bot_token');

        if (! is_string($chatId) || trim($chatId) === '' || ! is_string($botToken) || trim($botToken) === '') {
            Log::info('Telegram notification skipped because bot credentials are not configured.');

            return null;
        }

        try {
            $response = Http::timeout(5)->post(
                'https://api.telegram.org/bot'.$botToken.'/sendMessage',
                [
                    'chat_id' => trim($chatId),
                    'text' => $message,
                ],
            );
        } catch (ConnectionException $exception) {
            Log::warning('Telegram notification could not be delivered.', [
                'exception' => $exception::class,
            ]);

            return null;
        }

        if ($response->failed() || $response->json('ok') !== true) {
            Log::warning('Telegram notification was rejected.', [
                'status' => $response->status(),
                'description' => $response->json('description'),
            ]);

            return null;
        }

        return $response;
    }

    public function sendChatWaitingNotification(string $message): void
    {
        $chatId = config('services.telegram.chat_id');
        $botToken = config('services.telegram.bot_token');

        if (! is_string($chatId) || trim($chatId) === '' || ! is_string($botToken) || trim($botToken) === '') {
            $this->broadbandApplicaiton($message);

            return;
        }

        if ($this->broadbandApplicaiton($message) === null) {
            throw new RuntimeException('Telegram rejected or could not deliver the chat notification.');
        }
    }
}
