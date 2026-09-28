<?php

namespace App\Support;

use App\Models\Office;

final class TopUpCardOffices
{
    /**
     * @param list<int> $officeIds
     * @return list<string>
     */
    public static function resolveCodes(array $officeIds): array
    {
        $officeIds = array_values(array_unique(array_map('intval', $officeIds)));
        $query = Office::query()->orderBy('id');

        if ($officeIds !== []) {
            $query->whereIn('id', $officeIds);
        }

        return $query->pluck('cd')->map(fn($cd): string => (string) $cd)->unique()->values()->all();
    }
}