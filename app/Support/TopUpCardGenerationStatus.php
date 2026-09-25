<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class TopUpCardGenerationStatus
{
    /**
     * @return array{token: string, status: string|null, total_cards: int}|null
     */
    public static function forToken(?string $token): ?array
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $generation = Cache::get('top_up_card_generation:' . $token);

        if (!is_array($generation)) {
            return null;
        }

        $status = is_string($generation['status'] ?? null) ? $generation['status'] : null;
        $batch = Cache::get('top_up_card_generation:' . $token . ':batch');

        if (($batch['status'] ?? null) === 'completed') {
            $status = 'completed';
        } elseif (($batch['status'] ?? null) === 'failed') {
            $status = 'failed';
        }

        return [
            'token' => $token,
            'status' => $status,
            'total_cards' => (int) ($generation['total_cards'] ?? 0),
        ];
    }

    /**
     * @return array{token: string, status: string|null, total_cards: int}|null
     */
    public static function forSession(?string $token): ?array
    {
        return self::forToken($token);
    }
}
