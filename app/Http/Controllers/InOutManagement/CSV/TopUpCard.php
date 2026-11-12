<?php

namespace App\Http\Controllers\InOutManagement\CSV;

use App\Enums\TopUpCardStatus;
use App\Models\TopUpCard as TopUpCardModel;
use App\Support\Csv;
use App\Support\CsvImportException;
use InvalidArgumentException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TopUpCard
{
    /**
     * @var list<string>
     */
    private const ExportHeaders = ['SerialNumber', 'PIN_Code', 'PointValue', 'GenerateDate'];

    /**
     * @var list<string>
     */
    private const ImportHeaders = ['serial_no*', 'pin*', 'amount*', 'expires_at*', 'status*'];

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

    /**
     * @throws InvalidArgumentException when the CSV contents are invalid
     * @throws RuntimeException when the CSV cannot be read or cards are no longer pending
     */
    public static function import(UploadedFile $file): int
    {
        try {
            $rows = Csv::import($file, self::ImportHeaders);
        } catch (CsvImportException $exception) {
            if (
                $exception->translationKey === 'csv.import_errors.required_field' &&
                ($exception->replace['field'] ?? null) === 'serial_no'
            ) {
                throw new CsvImportException('csv.import_errors.invalid_row', [
                    'line' => (int) ($exception->replace['line'] ?? 0),
                ]);
            }

            throw $exception;
        }
        $serials = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $serial = trim((string) $row[0]);

            if (
                $serial === '' ||
                strtolower($serial) === 'null' ||
                trim((string) $row[4]) !== TopUpCardStatus::Pending->value
            ) {
                throw new CsvImportException('csv.import_errors.invalid_row', ['line' => $line]);
            }

            if (isset($serials[$serial])) {
                throw new CsvImportException('csv.import_errors.duplicate_serial', ['line' => $line]);
            }

            $serials[$serial] = $line;
        }
        if ($serials === []) {
            throw new CsvImportException('csv.import_errors.no_cards');
        }

        return DB::transaction(function () use ($serials): int {
            $cards = TopUpCardModel::query()->whereIn('serial_no', array_keys($serials))->lockForUpdate()->get();

            $cardsBySerial = $cards->keyBy('serial_no');

            foreach ($serials as $serial => $line) {
                $card = $cardsBySerial->get($serial);

                if ($card === null) {
                    throw new CsvImportException('csv.import_errors.serial_not_found', [
                        'serial' => $serial,
                        'line' => $line,
                    ]);
                }

                if ($card->status !== TopUpCardStatus::Pending) {
                    throw new CsvImportException('csv.import_errors.serial_not_pending', [
                        'serial' => $serial,
                        'line' => $line,
                    ]);
                }
            }

            $batchIds = $cards->pluck('batch_id')->filter()->unique()->values();
            $activationDate = now();
            $expiresAt = $activationDate->copy()->addYears(2)->toDateString();

            TopUpCardModel::query()
                ->whereIn('id', $cards->modelKeys())
                ->update([
                    'status' => TopUpCardStatus::Active,
                    'activated_at' => $activationDate->toDateString(),
                    'expires_at' => $expiresAt,
                    'updated_at' => $activationDate,
                ]);

            DB::table('batches')
                ->whereIn('id', $batchIds)
                ->update(['expires_at' => $expiresAt, 'updated_at' => $activationDate]);

            return (int) $batchIds->first();
        });
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
