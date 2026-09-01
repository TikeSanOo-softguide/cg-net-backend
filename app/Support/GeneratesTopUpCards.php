<?php

namespace App\Support;

use App\Enums\TopUpCardStatus;
use App\Models\TopUpCard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class GeneratesTopUpCards
{
    /**
     * @param  list<array{value: float|int|string, quantity: int}>  $amounts
     * @return list<array<string, mixed>>
     */
    public static function run(array $amounts, string $expiresAt, mixed $actor): array
    {
        return DB::transaction(function () use ($amounts, $expiresAt, $actor): array {
            $created = [];

            foreach ($amounts as $tier) {
                $quantity = (int) $tier['quantity'];

                for ($index = 0; $index < $quantity; $index++) {
                    $created[] = self::createCard((string) $tier['value'], $expiresAt);
                }
            }

            $total = array_reduce(
                $created,
                fn (float $sum, array $card): float => $sum + (float) $card['amount'],
                0.0,
            );

            activity('top-up-cards')
                ->causedBy($actor)
                ->event('generated')
                ->withProperties([
                    'count' => count($created),
                    'total_value' => number_format($total, 2, '.', ''),
                    'expires_at' => $expiresAt,
                ])
                ->log('top_up_cards_generated');

            return $created;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function createCard(string $amount, string $expiresAt): array
    {
        $pin = self::pin();
        $attempts = 0;

        while ($attempts < 8) {
            $attempts++;

            try {
                $card = TopUpCard::query()->create([
                    'serial_no' => self::serialNo(),
                    'pin' => Hash::make($pin),
                    'amount' => $amount,
                    'expires_at' => $expiresAt,
                    'status' => TopUpCardStatus::Valid,
                ]);

                return [
                    'id' => $card->id,
                    'serial_no' => $card->serial_no,
                    'pin' => $pin,
                    'amount' => $card->amount,
                    'expires_at' => $card->expires_at?->toDateString(),
                    'redeemed_at' => null,
                    'redeemed_by' => null,
                    'status' => $card->status->value,
                ];
            } catch (UniqueConstraintViolationException) {
                if ($attempts >= 8) {
                    throw new RuntimeException('Unable to allocate a unique top-up card serial.');
                }
            }
        }

        throw new RuntimeException('Unable to allocate a unique top-up card serial.');
    }

    public static function serialNo(): string
    {
        return 'TOPUP-'.self::segment().'-'.self::segment().'-'.self::segment();
    }

    public static function pin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private static function segment(): string
    {
        return strtoupper(bin2hex(random_bytes(2)));
    }
}
