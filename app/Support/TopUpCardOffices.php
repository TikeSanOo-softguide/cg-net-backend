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

    public static function officeCodeFromSerialNo(string $serialNo): ?string
    {
        if (preg_match('/^\d{10}(\d{2})\d+$/', $serialNo, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param list<string> $officeCodes
     * @return array<string, int>
     */
    public static function resolveIdsByCodes(array $officeCodes): array
    {
        $officeCodes = array_values(
            array_unique(
                array_filter(
                    array_map(static fn(string $code): string => trim($code), $officeCodes),
                    static fn(string $code): bool => preg_match('/^\d{2}$/', $code) === 1,
                ),
            ),
        );

        if ($officeCodes === []) {
            return [];
        }

        return Office::query()
            ->whereIn('cd', $officeCodes)
            ->get(['id', 'cd'])
            ->mapWithKeys(static fn(Office $office): array => [(string) $office->cd => (int) $office->id])
            ->all();
    }

    /**
     * @param list<string> $serialNumbers
     * @return array<string, int>
     */
    public static function resolveIdsBySerialNumbers(array $serialNumbers): array
    {
        $codesBySerial = [];

        foreach ($serialNumbers as $serialNumber) {
            $officeCode = self::officeCodeFromSerialNo($serialNumber);

            if ($officeCode !== null) {
                $codesBySerial[$serialNumber] = $officeCode;
            }
        }

        $officeIdsByCode = self::resolveIdsByCodes(array_values($codesBySerial));
        $officeIdsBySerial = [];

        foreach ($codesBySerial as $serialNumber => $officeCode) {
            if (isset($officeIdsByCode[$officeCode])) {
                $officeIdsBySerial[$serialNumber] = $officeIdsByCode[$officeCode];
            }
        }

        return $officeIdsBySerial;
    }
}
