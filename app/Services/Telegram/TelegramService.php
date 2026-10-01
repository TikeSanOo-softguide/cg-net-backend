<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramService
{
    public function sendMessage(string $message): Response
    {
        $response = Http::post(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
            [
                'chat_id' => config('services.telegram.chat_id'),
                'text' => $message,
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Telegram notification failed: ' . $response->body());
        }

        return $response;
    }
}
