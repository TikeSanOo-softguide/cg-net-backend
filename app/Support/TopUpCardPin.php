<?php

namespace App\Support;

use App\Models\TopUpCard;
use RuntimeException;

/**
 * Fast keyed digest for high-entropy top-up voucher PINs.
 * PIN-only HMAC so redemption can look up a card by PIN alone.
 */
final class TopUpCardPin
{
    public static function hash(string $pin): string
    {
        return hash_hmac('sha256', $pin, self::pepper());
    }

    public static function check(string $pin, string $hashedPin): bool
    {
        if ($hashedPin === '' || strlen($hashedPin) !== 64 || !ctype_xdigit($hashedPin)) {
            return false;
        }

        return hash_equals($hashedPin, self::hash($pin));
    }

    public static function findCard(string $pin): ?TopUpCard
    {
        return TopUpCard::query()->where('pin', self::hash($pin))->first();
    }

    private static function pepper(): string
    {
        $configured = (string) config('top_up_cards.pin_pepper', '');

        if ($configured !== '') {
            return $configured;
        }

        $appKey = (string) config('app.key', '');

        if ($appKey === '') {
            throw new RuntimeException('Unable to derive a top-up card PIN pepper without APP_KEY.');
        }

        return hash_hmac('sha256', 'top-up-card-pin-pepper', $appKey);
    }
}
