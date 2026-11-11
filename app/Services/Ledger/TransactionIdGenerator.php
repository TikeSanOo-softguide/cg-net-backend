<?php

namespace App\Services\Ledger;

use App\Enums\TransactionIdPrefix;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

class TransactionIdGenerator
{
    public const LENGTH = 16;

    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function generate(TransactionIdPrefix $prefix, ?DateTimeInterface $at = null): string
    {
        $randomLength = $prefix->randomLength();

        if ($randomLength < 1) {
            throw new InvalidArgumentException("Prefix [{$prefix->value}] cannot produce a 16-character transaction ID.");
        }

        $id = $prefix->value.($at ?? now())->format('ymd').$this->randomAlphanumeric($randomLength);

        if (strlen($id) !== self::LENGTH) {
            throw new RuntimeException('Generated transaction ID must be exactly '.self::LENGTH.' characters.');
        }

        return $id;
    }

    public function matches(string $transactionNo, TransactionIdPrefix $prefix): bool
    {
        $randomLength = $prefix->randomLength();
        $alphabet = preg_quote(self::ALPHABET, '/');

        return preg_match(
            '/^'.preg_quote($prefix->value, '/').'\d{6}['.$alphabet.']{'.$randomLength.'}$/',
            $transactionNo,
        ) === 1;
    }

    private function randomAlphanumeric(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $chars = '';

        for ($i = 0; $i < $length; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return $chars;
    }
}
