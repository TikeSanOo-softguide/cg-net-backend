<?php

namespace App\Support;

use App\Enums\BatchStatus;
use App\Enums\TopUpCardStatus;
use App\Models\Batch;
use App\Models\TopUpCard;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GeneratesTopUpCards
{
    public const DAILY_COUNTER_START = 1001;

    public const DAILY_COUNTER_END = 9999;

    public const DAILY_COUNTER_CAPACITY = self::DAILY_COUNTER_END - self::DAILY_COUNTER_START + 1;

    /**
     * @param  list<array{value: int|string, quantity: int}>  $amounts
     * @return list<array<string, mixed>>
     */
    public static function run(
        array $amounts,
        string $expiresAt,
        mixed $actor,
        int $startingCounter = 1001,
        ?CarbonInterface $productionDate = null,
        ?string $officeCode = null,
        ?int $batchId = null,
    ): array {
        $productionDate ??= now();
        $officeCode ??= '88';

        $materialized = self::materialize(
            $amounts,
            $expiresAt,
            $startingCounter,
            $productionDate,
            $officeCode,
            batchId: null,
        );

        return $materialized['cards'];
    }

    /**
     * Build card payloads for CSV export and optional rows for a reserved serial range.
     *
     * @param  list<array{value: int|string, quantity: int}>  $amounts
     * @return array{
     *     batch_id: int|null,
     *     starting_counter: int,
     *     cards: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public static function materialize(
        array $amounts,
        string $expiresAt,
        int $startingCounter,
        ?CarbonInterface $productionDate = null,
        ?string $officeCode = null,
        ?int $batchId = null,
    ): array {
        $productionDate ??= now();
        $officeCode ??= '88';
        $dailyCounter = $startingCounter;
        $cards = [];
        $rows = [];

        foreach ($amounts as $tier) {
            $quantity = (int) $tier['quantity'];
            $amount = (string) (int) $tier['value'];

            $built = self::buildCardRows(
                $amount,
                $expiresAt,
                $dailyCounter,
                $batchId,
                $productionDate,
                $quantity,
                $officeCode,
            );

            $cards = [...$cards, ...$built['cards']];
            $rows = [...$rows, ...$built['rows']];
        }

        return [
            'batch_id' => $batchId,
            'starting_counter' => $startingCounter,
            'cards' => $cards,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function insertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('top_up_card')->insert($rows);
    }

    /**
     * @param  list<array{serial_no?: string}>  $cards
     */
    public static function allSerialsExist(array $cards): bool
    {
        $serialNumbers = array_values(
            array_filter(
                array_map(
                    static fn(array $card): string => (string) ($card['serial_no'] ?? ''),
                    $cards,
                ),
            ),
        );

        if ($serialNumbers === [] || count($serialNumbers) !== count($cards)) {
            return false;
        }

        $existing = DB::table('top_up_card')
            ->whereIn('serial_no', $serialNumbers)
            ->count();

        return $existing === count($serialNumbers);
    }

    /**
     * @return array{cards: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function buildCardRows(
        string $amount,
        string $expiresAt,
        int &$dailyCounter,
        ?int $batchId,
        CarbonInterface $productionDate,
        int $quantity,
        string $officeCode = '88',
    ): array {
        $rangeStart = $dailyCounter;
        $officeId = $batchId === null
            ? null
            : (TopUpCardOffices::resolveIdsByCodes([$officeCode])[$officeCode] ?? null);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $rows = [];
            $cards = [];
            $usedPinHashes = [];
            $counter = $rangeStart;
            $now = now();

            for ($index = 0; $index < $quantity; $index++) {
                $serialNo = self::serialNo($amount, $counter, $productionDate, $officeCode);
                $pin = self::uniquePlainPin($usedPinHashes);
                $pinHash = TopUpCardPin::hash($pin);
                $usedPinHashes[$pinHash] = true;

                if ($batchId !== null) {
                    $rows[] = [
                        'serial_no' => $serialNo,
                        'pin' => $pinHash,
                        'amount' => $amount,
                        'activated_at' => $now->toDateString(),
                        'expires_at' => $expiresAt,
                        'status' => TopUpCardStatus::Active->value,
                        'generate_date' => $now->toDateString(),
                        'office_id' => $officeId,
                        'batch_id' => $batchId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $cards[] = [
                    'id' => null,
                    'serial_no' => $serialNo,
                    'pin' => $pin,
                    'amount' => $amount,
                    'expires_at' => $expiresAt,
                    'activated_at' => $now->toDateString(),
                    'generate_date' => $now->toDateString(),
                    'redeemed_at' => null,
                    'redeemed_by' => null,
                    'status' => TopUpCardStatus::Active->value,
                ];

                $counter = self::nextDailyCounter($counter);
            }

            $pinHashes = array_column($rows, 'pin');

            if (
                $pinHashes !== [] &&
                DB::table('top_up_card')->whereIn('pin', $pinHashes)->exists()
            ) {
                continue;
            }

            $dailyCounter = $counter;

            return [
                'cards' => $cards,
                'rows' => $rows,
            ];
        }

        throw new RuntimeException('Unable to create unique top-up card PINs after several attempts.');
    }
    /**
     * @param  array<string, true>  $usedPinHashes
     */
    private static function uniquePlainPin(array &$usedPinHashes): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $pin = self::pin();
            $hash = TopUpCardPin::hash($pin);

            if (!isset($usedPinHashes[$hash])) {
                return $pin;
            }
        }

        throw new RuntimeException('Unable to allocate a unique top-up card PIN.');
    }

    public static function allocateSerialRange(
        int $quantity,
        ?CarbonInterface $productionDate = null,
        ?string $officeCode = null,
        ?string $batchCode = null,
    ): int {
        if ($quantity < 1) {
            throw new RuntimeException('The serial range must contain at least one card.');
        }

        $productionDate ??= now();
        $sequenceDate = $productionDate->toDateString();
        $officeCode ??= '88';
        $batchCode ??= '1101';

        $execute = function () use (
            $quantity,
            $sequenceDate,
            $officeCode,
            $batchCode,
        ): int {
            DB::table('top_up_card_sequences')->insertOrIgnore([
                'sequence_date' => $sequenceDate,
                'office_code' => $officeCode,
                'batch_code' => $batchCode,
                'next_counter' => self::DAILY_COUNTER_START,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('top_up_card_sequences')
                ->where('sequence_date', $sequenceDate)
                ->where('office_code', $officeCode)
                ->where('batch_code', $batchCode)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException('Unable to lock the top-up card serial sequence.');
            }

            $startingCounter = (int) $sequence->next_counter;
            $endingCounter = $startingCounter + $quantity - 1;

            if ($endingCounter > self::DAILY_COUNTER_END) {
                throw new RuntimeException('The daily top-up card serial range is exhausted.');
            }

            DB::table('top_up_card_sequences')
                ->where('sequence_date', $sequenceDate)
                ->where('office_code', $officeCode)
                ->where('batch_code', $batchCode)
                ->update([
                    'next_counter' => $endingCounter + 1,
                    'updated_at' => now(),
                ]);

            return $startingCounter;
        };

        return DB::transactionLevel() > 0 ? $execute() : DB::transaction($execute);
    }

    public static function reserveSerialRange(
        int $quantity,
        ?CarbonInterface $productionDate = null,
        ?string $officeCode = null,
        ?string $batchCode = null,
    ): int {
        return self::allocateSerialRange($quantity, $productionDate, $officeCode, $batchCode);
    }

    public static function serialNo(
        string $amount = '500',
        ?int $dailyCounter = null,
        ?CarbonInterface $productionDate = null,
        string $officeCode = '88',
    ): string {
        $productionDate ??= now();
        $dailyCounter ??= random_int(self::DAILY_COUNTER_START, self::DAILY_COUNTER_END);

        if ($dailyCounter < self::DAILY_COUNTER_START || $dailyCounter > self::DAILY_COUNTER_END) {
            throw new RuntimeException('The daily top-up card counter must be between 1001 and 9999.');
        }

        if (preg_match('/^\d{2}$/', $officeCode) !== 1) {
            throw new RuntimeException('The top-up card office code must contain exactly two digits.');
        }

        return sprintf(
            '%s%s%02d%s%04d',
            $productionDate->format('ym'),
            self::batchCodeForAmount($amount),
            (int) $productionDate->format('d'),
            $officeCode,
            $dailyCounter,
        );
    }

    public static function pin(): string
    {
        return (string) random_int(1000000000000000, 9999999999999999);
    }

    /**
     * Create the single Batch row shared by every chunk of a generation run.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function nextBatchNo(?CarbonInterface $date = null): string
    {
        $date ??= now(config('app.timezone'));
        $prefix = $date->format('YmdHi');
        $counter = 1001;

        foreach (Batch::query()->where('batch_no', 'like', $prefix . '%')->pluck('batch_no') as $batchNo) {
            $suffix = substr((string) $batchNo, strlen($prefix));

            if (preg_match('/^\d+$/', $suffix) === 1) {
                $counter = max($counter, (int) $suffix + 1);
            }
        }

        return $prefix . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
    }

    public static function createGenerationBatch(
        string $expiresAt,
        int $quantity,
        int|float|string $totalValue,
        array $metadata = [],
        ?string $token = null,
    ): Batch {
        if ($token !== null && $token !== '') {
            $metadata['generation_token'] = $token;
        }

        return Batch::query()->create([
            'batch_no' => self::nextBatchNo(),
            'total_value' => $totalValue,
            'quantity' => $quantity,
            'status' => BatchStatus::Active,
            'expires_at' => $expiresAt,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    private static function nextDailyCounter(int $dailyCounter): int
    {
        return $dailyCounter + 1;
    }

    public static function batchCodeForAmount(string $amount): string
    {
        $normalized = (int) round((float) $amount);

        $batchCode = match ($normalized) {
            50 => '1101',
            100 => '1201',
            250 => '1501',
            500 => '1111',
            5 => '9005',
            10 => '9010',
            20 => '9020',
            30 => '9030',
            35 => '9035',
            default => str_pad((string) abs($normalized % 10000), 4, '0', STR_PAD_LEFT),
        };

        return $batchCode;
    }
}
