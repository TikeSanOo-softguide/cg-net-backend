<?php

namespace App\Http\Controllers\InOutManagement\CSV;

use App\Support\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TopUpCard
{
    /**
     * @var list<string>
     */
    private const ExportHeaders = ['SerialNumber', 'PIN_Code', 'PointValue', 'GenerateDate'];

    /**
     * @param list<array<string, mixed>> $cards
     */
    public static function export(array $cards, string $filename = 'top-up-cards.csv'): StreamedResponse
    {
        return Csv::export(
            self::ExportHeaders,
            array_map(
                fn(array $card): array => [
                    $card['serial_no'] ?? '',
                    self::formatPin($card['pin'] ?? ''),
                    self::formatAmount($card['amount'] ?? 0),
                    $card['generate_date'] ?? '',
                ],
                $cards,
            ),
            $filename,
        );
    }

    private static function formatAmount(mixed $amount): string
    {
        $numericAmount = is_numeric($amount) ? (string) ((float) $amount) : '0';
        $numericAmount = preg_replace('/\.0+$/', '', $numericAmount);

        return preg_replace('/(\.\d*?)0+$/', '$1', $numericAmount) ?? $numericAmount;
    }

    private static function formatPin(mixed $pin): string
    {
        $digits = preg_replace('/\D/', '', (string) $pin) ?? '';

        return implode('-', str_split($digits, 4));
    }
}
