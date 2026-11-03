<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

final class GeneratesTicketNumber
{
    /**
     * Next sequential ticket for a prefix (must be called inside a DB transaction; uses lockForUpdate).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function next(
        string $modelClass,
        string $column,
        string $prefix,
        int $pad = 4,
    ): string {
        $max = $modelClass::withTrashed()
            ->where($column, 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck($column)
            ->reduce(function (int $highest, ?string $number) use ($prefix): int {
                $sequence = (int) substr((string) $number, strlen($prefix));

                return max($highest, $sequence);
            }, 0);

        return $prefix.str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
