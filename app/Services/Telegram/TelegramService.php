<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Telegram notification was rejected.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response;
    }
}
