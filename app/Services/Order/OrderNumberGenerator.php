<?php

namespace App\Services\Order;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderNumberGenerator
{
    public const PREFIX = 'ORD';

    public const LENGTH = 16;

    public const SEQUENCE_WIDTH = 7;

    public const MAX_DAILY_SEQUENCE = 9_999_999;

    public function generate(?DateTimeInterface $at = null): string
    {
        $at ??= now();
        $dateKey = $at->format('Y-m-d');
        $datePart = $at->format('ymd');

        $execute = function () use ($dateKey, $datePart): string {
            DB::table('order_number_sequences')->insertOrIgnore([
                'sequence_date' => $dateKey,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('order_number_sequences')
                ->where('sequence_date', $dateKey)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException('Unable to lock the order number sequence.');
            }

            $next = (int) $sequence->next_number;

            if ($next < 1 || $next > self::MAX_DAILY_SEQUENCE) {
                throw new RuntimeException('The daily order number sequence is exhausted.');
            }

            DB::table('order_number_sequences')
                ->where('sequence_date', $dateKey)
                ->update([
                    'next_number' => $next + 1,
                    'updated_at' => now(),
                ]);

            $orderNo = self::PREFIX.$datePart.str_pad((string) $next, self::SEQUENCE_WIDTH, '0', STR_PAD_LEFT);

            if (strlen($orderNo) !== self::LENGTH) {
                throw new RuntimeException('Generated order number must be exactly '.self::LENGTH.' characters.');
            }

            return $orderNo;
        };

        return DB::transactionLevel() > 0
            ? $execute()
            : DB::transaction($execute, 5);
    }

    public function matches(string $orderNo): bool
    {
        return preg_match('/^'.self::PREFIX.'\d{6}\d{'.self::SEQUENCE_WIDTH.'}$/', $orderNo) === 1
            && strlen($orderNo) === self::LENGTH;
    }
}
