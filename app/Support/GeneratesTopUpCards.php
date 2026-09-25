<?php

namespace App\Support;

use App\Enums\BatchStatus;
use App\Enums\TopUpCardStatus;
use App\Models\Batch;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GeneratesTopUpCards
{
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
        ?string $agentCode = null,
        ?int $batchId = null,
    ): array {
        $productionDate ??= now();
        $agentCode ??= '88';

        $execute = function () use (
            $amounts,
            $expiresAt,
            $startingCounter,
            $productionDate,
            $agentCode,
            $batchId,
        ): array {
            $resolvedBatchId = $batchId;

            if ($resolvedBatchId === null) {
                $quantity = 0;
                $totalValue = 0;
                $items = [];

                foreach ($amounts as $tier) {
                    $tierAmount = (int) $tier['value'];
                    $tierQuantity = (int) $tier['quantity'];
                    $quantity += $tierQuantity;
                    $totalValue += $tierAmount * $tierQuantity;
                    $items[] = [
                        'agent_cd' => $agentCode,
                        'amount' => $tierAmount,
                        'quantity' => $tierQuantity,
                    ];
                }

                $resolvedBatchId = self::createGenerationBatch(
                    $expiresAt,
                    $quantity,
                    $totalValue,
                    ['items' => $items],
                )->id;
            }

            $materialized = self::materialize(
                $amounts,
                $expiresAt,
                $startingCounter,
                $productionDate,
                $agentCode,
                $resolvedBatchId,
            );

            self::insertRows($materialized['rows']);

            return $materialized['cards'];
        };

        return DB::transactionLevel() > 0 ? $execute() : DB::transaction($execute);
    }

    /**
     * Build insert rows and plaintext card payloads for a reserved serial range.
     *
     * @param  list<array{value: int|string, quantity: int}>  $amounts
     * @return array{
     *     batch_id: int,
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
        ?string $agentCode = null,
        ?int $batchId = null,
    ): array {
        if ($batchId === null || $batchId < 1) {
            throw new RuntimeException('A shared batch id is required to materialize top-up cards.');
        }

        $productionDate ??= now();
        $agentCode ??= '88';
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
                $agentCode,
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
     * Rebuild hashed insert rows from cached plaintext card payloads.
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    public static function rowsFromCards(array $cards, int $batchId): array
    {
        $now = now();
        $rows = [];

        foreach ($cards as $card) {
            $pin = (string) ($card['pin'] ?? '');
            $serialNo = (string) ($card['serial_no'] ?? '');

            if ($pin === '' || $serialNo === '') {
                throw new RuntimeException('Cached top-up card payload is missing serial or PIN.');
            }

            $rows[] = [
                'serial_no' => $serialNo,
                'pin' => TopUpCardPin::hash($pin),
                'amount' => (string) ($card['amount'] ?? ''),
                'expires_at' => $card['expires_at'] ?? null,
                'status' => TopUpCardStatus::Pending->value,
                'agent_id' => null,
                'batch_id' => $batchId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
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
        int $batchId,
        CarbonInterface $productionDate,
        int $quantity,
        string $agentCode = '88',
    ): array {
        $rangeStart = $dailyCounter;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $rows = [];
            $cards = [];
            $usedPinHashes = [];
            $counter = $rangeStart;
            $now = now();

            for ($index = 0; $index < $quantity; $index++) {
                $serialNo = self::serialNo($amount, $counter, $productionDate, $agentCode);
                $pin = self::uniquePlainPin($usedPinHashes);
                $pinHash = TopUpCardPin::hash($pin);
                $usedPinHashes[$pinHash] = true;

                $rows[] = [
                    'serial_no' => $serialNo,
                    'pin' => $pinHash,
                    'amount' => $amount,
                    'expires_at' => $expiresAt,
                    'status' => TopUpCardStatus::Pending->value,
                    'agent_id' => null,
                    'batch_id' => $batchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $cards[] = [
                    'id' => null,
                    'serial_no' => $serialNo,
                    'pin' => $pin,
                    'amount' => $amount,
                    'expires_at' => $expiresAt,
                    'redeemed_at' => null,
                    'redeemed_by' => null,
                    'status' => TopUpCardStatus::Pending->value,
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
        ?string $agentCode = null,
        ?string $batchCode = null,
    ): int {
        if ($quantity < 1) {
            throw new RuntimeException('The serial range must contain at least one card.');
        }

        $productionDate ??= now();
        $sequenceDate = $productionDate->toDateString();
        $agentCode ??= '88';
        $batchCode ??= '1101';

        $execute = function () use (
            $quantity,
            $sequenceDate,
            $agentCode,
            $batchCode,
        ): int {
            DB::table('top_up_card_sequences')->insertOrIgnore([
                'sequence_date' => $sequenceDate,
                'agent_code' => $agentCode,
                'batch_code' => $batchCode,
                'next_counter' => 1001,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('top_up_card_sequences')
                ->where('sequence_date', $sequenceDate)
                ->where('agent_code', $agentCode)
                ->where('batch_code', $batchCode)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException('Unable to lock the top-up card serial sequence.');
            }

            $startingCounter = (int) $sequence->next_counter;
            $endingCounter = $startingCounter + $quantity - 1;

            if ($endingCounter > 999999) {
                throw new RuntimeException('The daily top-up card serial range is exhausted.');
            }

            DB::table('top_up_card_sequences')
                ->where('sequence_date', $sequenceDate)
                ->where('agent_code', $agentCode)
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
        ?string $agentCode = null,
        ?string $batchCode = null,
    ): int {
        return self::allocateSerialRange($quantity, $productionDate, $agentCode, $batchCode);
    }

    public static function serialNo(
        string $amount = '500',
        ?int $dailyCounter = null,
        ?CarbonInterface $productionDate = null,
        string $agentCode = '88',
    ): string {
        $productionDate ??= now();
        $dailyCounter ??= random_int(1001, 9999);

        return sprintf(
            '%s%s%02d%02d%06d',
            $productionDate->format('ym'),
            self::batchCodeForAmount($amount),
            (int) $productionDate->format('d'),
            $agentCode,
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
            'batch_no' => now('Asia/Yangon')->format('YmdHisv'),
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

        return match ($normalized) {
            50 => '1101',
            100 => '1201',
            250 => '1501',
            500 => '1111',
            default => str_pad((string) abs($normalized % 10000), 4, '0', STR_PAD_LEFT),
        };
    }
}
