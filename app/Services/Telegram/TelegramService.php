<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramService
{
    public function broadbandApplicaiton(string $message, ?string $chatId = null): Response
    {
        $chatId ??= config('services.telegram.chat_id');

        return $this->sendMessage($chatId, $message);
    }

    private function sendMessage(string $chatId, string $message): Response
    {
        $response = Http::post(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
            [
                'chat_id' => $chatId,
                'text' => $message,
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Telegram notification failed: ' . $response->body());
        }

        return $response;
    }
}
