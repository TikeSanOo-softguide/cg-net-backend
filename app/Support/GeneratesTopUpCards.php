<?php

namespace App\Support;

use App\Enums\TopUpCardStatus;
use App\Models\Batch;
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
            $dailyCounter = self::startingDailyCounter();

            foreach ($amounts as $tier) {
                $quantity = (int) $tier['quantity'];
                $batch = self::ensureBatch((string) $tier['value'], $expiresAt, $quantity);

                for ($index = 0; $index < $quantity; $index++) {
                    $created[] = self::createCard((string) $tier['value'], $expiresAt, $dailyCounter, $batch->id);
                }
            }

            $total = array_reduce(
                $created,
                fn(float $sum, array $card): float => $sum + (float) $card['amount'],
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
    private static function createCard(string $amount, string $expiresAt, int &$dailyCounter, int $batchId): array
    {
        $pin = self::ensureUniquePin();
        $attempts = 0;
        $maxAttempts = 9000;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                $serialNo = self::allocateUniqueSerial($amount, $dailyCounter);
                $card = TopUpCard::query()->create([
                    'serial_no' => $serialNo,
                    'pin' => Hash::make($pin),
                    'amount' => $amount,
                    'expires_at' => $expiresAt,
                    'status' => TopUpCardStatus::Active,
                    'batch_id' => $batchId,
                ]);

                $dailyCounter = self::nextDailyCounter($dailyCounter);

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
                $dailyCounter = self::nextDailyCounter($dailyCounter);
            }
        }

        throw new RuntimeException('Unable to allocate a unique top-up card serial.');
    }

    private static function allocateUniqueSerial(string $amount, int &$dailyCounter): string
    {
        $attempts = 0;
        $maxAttempts = 9000;

        while ($attempts < $maxAttempts) {
            $serialNo = self::serialNo($amount, $dailyCounter);

            if (!TopUpCard::query()->where('serial_no', $serialNo)->exists()) {
                return $serialNo;
            }

            $dailyCounter = self::nextDailyCounter($dailyCounter);
            $attempts++;
        }

        throw new RuntimeException('Unable to allocate a unique top-up card serial.');
    }

    public static function serialNo(string $amount = '500', ?int $dailyCounter = null): string
    {
        $productionDate = now();
        $dailyCounter ??= random_int(1001, 9999);

        return sprintf(
            '%s%s%02d%02d%04d',
            $productionDate->format('ym'),
            self::batchCodeForAmount($amount),
            (int) $productionDate->format('d'),
            88,
            $dailyCounter,
        );
    }

    public static function pin(): string
    {
        return (string) random_int(1000000000000000, 9999999999999999);
    }

    private static function ensureUniquePin(): string
    {
        $candidate = self::pin();

        while (TopUpCard::query()->whereNotNull('pin')->get()->contains(
            fn(TopUpCard $card): bool => Hash::check($candidate, $card->pin),
        )) {
            $candidate = self::pin();
        }

        return $candidate;
    }

    private static function ensureBatch(string $amount, string $expiresAt, int $quantity): Batch
    {

        return Batch::query()->create([
            'batch_no' => now('Asia/Yangon')->format('ymdHis'),
            'amount' => $amount,
            'quantity' => $quantity,
            'status' => 'active',
            'expires_at' => $expiresAt,
        ]);
    }

    private static function startingDailyCounter(): int
    {
        return 1001;
    }

    private static function nextDailyCounter(int $dailyCounter): int
    {
        $next = $dailyCounter + 1;

        return $next > 9999 ? 1001 : $next;
    }

    private static function batchCodeForAmount(string $amount): string
    {
        $normalized = (int) round((float) $amount);

        return match ($normalized) {
            50 => '1101',
            100 => '1201',
            250 => '1501',
            500 => '1111',
            default => str_pad((string) abs($normalized % 10000), 4, '0', STR_PAD_LEFT),
        };
    }
}
