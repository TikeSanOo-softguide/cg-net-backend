<?php

namespace App\Jobs;

use App\Enums\TopUpCardStatus;
use App\Models\Batch;
use App\Models\Office;
use App\Support\CsvImportException;
use App\Support\GeneratesTopUpCards;
use App\Support\TopUpCardOffices;
use App\Support\TopUpCardPin;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportGeneratedTopUpCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    /** @var array<string, true>|null */
    private ?array $validOfficeCodes = null;

    public function __construct(
        public readonly string $path,
        public readonly string $token,
        public readonly int $userId,
    ) {
        $this->timeout = (int) config('horizon.top_up_cards.timeout', 300);
        $this->onQueue('top-up-cards');
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return array{rows: list<array{office: string, count_50: int, count_100: int, count_250: int, count_500: int, total_points: int, expires_at: string}>, total_rows: int, total_summaries: int}
     */
    public static function previewCsv(string $path): array
    {
        return (new self($path, 'validation-preview', 0))->readCsvPreview();
    }

    /**
     * @return array{rows: list<array{office: string, count_50: int, count_100: int, count_250: int, count_500: int, total_points: int, expires_at: string}>, total_rows: int, total_summaries: int}
     */
    private function readCsvPreview(): array
    {
        $stream = $this->openCsvStream();
        $totalRows = 0;
        $summaries = [];
        $seenSerials = [];
        $seenPins = [];

        try {
            $format = $this->readHeaders($stream);

            while (($row = fgetcsv($stream, 0, $format['delimiter'])) !== false) {
                $line = $totalRows + 2;
                $card = $this->parseRow($row, $line, $format);
                $pinHash = TopUpCardPin::hash($card['pin']);

                if (isset($seenSerials[$card['serial_no']])) {
                    throw new CsvImportException('csv.import_errors.duplicate_serial', ['line' => $line]);
                }

                if (isset($seenPins[$pinHash])) {
                    throw new CsvImportException('csv.import_errors.duplicate_pin', ['line' => $line]);
                }

                $seenSerials[$card['serial_no']] = true;
                $seenPins[$pinHash] = true;
                $totalRows++;
                $officeCode = TopUpCardOffices::officeCodeFromSerialNo($card['serial_no']);
                $summaryKey = ($officeCode ?? '') . ':' . $card['expires_at'];
                $summaries[$summaryKey] ??= [
                    'office_code' => $officeCode,
                    'count_50' => 0,
                    'count_100' => 0,
                    'count_250' => 0,
                    'count_500' => 0,
                    'total_points' => 0,
                    'expires_at' => $card['expires_at'],
                ];

                $countKey = match ($card['amount']) {
                    50 => 'count_50',
                    100 => 'count_100',
                    250 => 'count_250',
                    500 => 'count_500',
                    default => null,
                };

                if ($countKey !== null) {
                    $summaries[$summaryKey][$countKey]++;
                }
                $summaries[$summaryKey]['total_points']++;

                if ($totalRows > (int) config('top_up_cards.max_cards', 100000)) {
                    throw new CsvImportException('csv.import_errors.too_many_cards', [
                        'max' => (int) config('top_up_cards.max_cards', 100000),
                    ]);
                }
            }
        } finally {
            fclose($stream);
        }

        if ($totalRows === 0) {
            throw new CsvImportException('csv.import_errors.no_cards');
        }

        $totalSummaries = count($summaries);
        $previewLimit = min(200, max(1, (int) config('top_up_cards.preview_limit', 100)));
        $summaries = array_slice(array_values($summaries), 0, $previewLimit);
        $officeCodes = array_values(array_unique(array_filter(array_column($summaries, 'office_code'))));
        $officesByCode = Office::query()
            ->whereIn('cd', $officeCodes)
            ->get(['name', 'cd'])
            ->keyBy(fn(Office $office): string => (string) $office->cd);
        $rows = array_map(function (array $summary) use ($officesByCode): array {
            $officeCode = $summary['office_code'];
            $office = $officeCode === null ? null : $officesByCode->get($officeCode);

            return [
                'office' => $office ? $office->name : '—',
                'count_50' => $summary['count_50'],
                'count_100' => $summary['count_100'],
                'count_250' => $summary['count_250'],
                'count_500' => $summary['count_500'],
                'total_points' => $summary['total_points'],
                'expires_at' => $summary['expires_at'],
            ];
        }, array_values($summaries));

        return [
            'rows' => $rows,
            'total_rows' => $totalRows,
            'total_summaries' => $totalSummaries,
        ];
    }

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $cachedGeneration = Cache::get($this->generationKey());
        $cachedBatch = Cache::get($this->batchKey());

        if (($cachedGeneration['status'] ?? null) === 'completed' && ($cachedBatch['status'] ?? null) === 'completed') {
            $disk->delete($this->path);

            return;
        }

        $stream = $this->openCsvStream();
        try {
            $totalCards = 0;
            $totalValue = 0;
            $amountBreakdown = [];
            $metadataItems = [];
            $seenSerials = [];
            $seenPins = [];
            $firstExpiry = null;
            $chunkSize = max(1, (int) config('top_up_cards.chunk_size', 1000));
            $format = $this->readHeaders($stream);

            while (($row = fgetcsv($stream, 0, $format['delimiter'])) !== false) {
                $line = $totalCards + 2;
                $card = $this->parseRow($row, $line, $format);
                $firstExpiry ??= $card['expires_at'];

                if (isset($seenSerials[$card['serial_no']])) {
                    throw new CsvImportException('csv.import_errors.duplicate_serial', ['line' => $line]);
                }

                $pinHash = TopUpCardPin::hash($card['pin']);

                if (isset($seenPins[$pinHash])) {
                    throw new CsvImportException('csv.import_errors.duplicate_pin', ['line' => $line]);
                }

                $seenSerials[$card['serial_no']] = true;
                $seenPins[$pinHash] = true;
                $amountBreakdown[$card['amount']] = ($amountBreakdown[$card['amount']] ?? 0) + 1;
                $officeCode = TopUpCardOffices::officeCodeFromSerialNo($card['serial_no']);

                if ($officeCode !== null) {
                    $itemKey = $officeCode . ':' . $card['amount'];
                    $metadataItems[$itemKey] ??= [
                        'office_cd' => $officeCode,
                        'amount' => $card['amount'],
                        'quantity' => 0,
                    ];
                    $metadataItems[$itemKey]['quantity']++;
                }

                $totalCards++;
                $totalValue += $card['amount'];

                if ($totalCards > (int) config('top_up_cards.max_cards', 100000)) {
                    throw new CsvImportException('csv.import_errors.too_many_cards', [
                        'max' => (int) config('top_up_cards.max_cards', 100000),
                    ]);
                }
            }

            if ($totalCards === 0) {
                throw new CsvImportException('csv.import_errors.no_cards');
            }
        } finally {
            fclose($stream);
        }

        $stream = $this->openCsvStream();
        try {
            $format = $this->readHeaders($stream);
            $validationChunk = [];
            $validationChunkIndex = 0;
            $validatedRows = 0;

            while (($row = fgetcsv($stream, 0, $format['delimiter'])) !== false) {
                $validationChunk[] = $this->parseRow($row, $validatedRows + 2, $format);
                $validatedRows++;

                if (count($validationChunk) >= $chunkSize) {
                    $this->assertChunkDoesNotExist(
                        $validationChunk,
                        $validatedRows - count($validationChunk) + 2,
                        $validationChunkIndex,
                    );
                    $validationChunk = [];
                    $validationChunkIndex++;
                }
            }

            if ($validationChunk !== []) {
                $this->assertChunkDoesNotExist(
                    $validationChunk,
                    $validatedRows - count($validationChunk) + 2,
                    $validationChunkIndex,
                );
            }
        } finally {
            fclose($stream);
        }

        try {
            $stream = $this->openCsvStream();
            $format = $this->readHeaders($stream);
            $totalChunks = (int) ceil($totalCards / $chunkSize);
            $amounts = array_map(
                static fn(int $amount, int $count): array => [
                    'amount' => $amount,
                    'cards' => $count,
                    'value' => $amount * $count,
                ],
                array_keys($amountBreakdown),
                array_values($amountBreakdown),
            );
            $preview = [];
            $previewLimit = max(0, (int) config('top_up_cards.preview_limit', 100));
            $importBatch = null;

            try {
                DB::transaction(function () use (
                    &$importBatch,
                    &$preview,
                    $stream,
                    $format,
                    $totalCards,
                    $totalValue,
                    $totalChunks,
                    $firstExpiry,
                    $amounts,
                    $metadataItems,
                    $chunkSize,
                    $previewLimit,
                ): void {
                    $importBatch = Batch::query()
                        ->where('metadata->generation_token', $this->token)
                        ->latest('id')
                        ->first();

                    if (!($importBatch instanceof Batch)) {
                        $importBatch = Batch::query()->create([
                            'batch_no' => GeneratesTopUpCards::nextBatchNo(),
                            'status' => \App\Enums\BatchStatus::Active,
                            'expires_at' => $firstExpiry ?? now()->toDateString(),
                        ]);
                    }

                    Cache::put(
                        $this->generationKey(),
                        [
                            'status' => 'processing',
                            'total_cards' => $totalCards,
                            'completed_cards' => 0,
                            'total_value' => (string) $totalValue,
                            'total_chunks' => $totalChunks,
                            'completed_chunks' => 0,
                            'expires_at' => $firstExpiry,
                            'amounts' => $amounts,
                            'user_id' => $this->userId,
                            'source' => 'csv_import',
                            'batch_id' => $importBatch->id,
                            'top_up_batch_id' => $importBatch->id,
                            'top_up_batch_no' => $importBatch->batch_no,
                        ],
                        now()->addDay(),
                    );

                    Cache::put(
                        $this->batchKey(),
                        ['status' => 'processing', 'batch_id' => $importBatch->id],
                        now()->addDay(),
                    );

                    $chunk = [];
                    $chunkIndex = 0;

                    while (($row = fgetcsv($stream, 0, $format['delimiter'])) !== false) {
                        $chunk[] = $this->parseRow($row, $chunkIndex * $chunkSize + count($chunk) + 2, $format);

                        if (count($chunk) >= $chunkSize) {
                            $preview = $this->insertChunk(
                                $chunk,
                                $chunkIndex,
                                $preview,
                                $previewLimit,
                                (int) $importBatch->id,
                            );
                            $chunk = [];
                            $chunkIndex++;
                        }
                    }

                    if ($chunk !== []) {
                        $preview = $this->insertChunk(
                            $chunk,
                            $chunkIndex,
                            $preview,
                            $previewLimit,
                            (int) $importBatch->id,
                        );
                        $chunkIndex++;
                    }

                    Batch::query()
                        ->whereKey($importBatch->id)
                        ->update([
                            'total_value' => $totalValue,
                            'quantity' => $totalCards,
                            'status' => \App\Enums\BatchStatus::Active,
                            'expires_at' => $firstExpiry ?? now()->toDateString(),
                            'metadata' => [
                                'total_cards' => $totalCards,
                                'total_value' => (string) $totalValue,
                                'items' => array_values($metadataItems),
                                'user_id' => $this->userId,
                            ],
                        ]);
                });
            } catch (Throwable $exception) {
                for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
                    Cache::forget($this->generationKey() . ':chunk:' . $chunkIndex);
                }

                throw $exception;
            }

            if (!($importBatch instanceof Batch)) {
                throw new RuntimeException('CSV import batch was not created.');
            }

            Cache::put(
                $this->generationKey(),
                [
                    'status' => 'completed',
                    'total_cards' => $totalCards,
                    'completed_cards' => $totalCards,
                    'total_value' => (string) $totalValue,
                    'total_chunks' => $totalChunks,
                    'completed_chunks' => $totalChunks,
                    'expires_at' => $firstExpiry,
                    'amounts' => $amounts,
                    'user_id' => $this->userId,
                    'source' => 'csv_import',
                    'cards' => $preview,
                ],
                now()->addDay(),
            );
            Cache::put($this->batchKey(), ['status' => 'completed', 'batch_id' => $importBatch->id], now()->addDay());
            if (Cache::get('top_up_card_generation:active') === $this->token) {
                Cache::forget('top_up_card_generation:active');
            }
            Cache::forget('top_up_cards.latest_expires_at');
            Cache::forget('top_up_cards.amount_options');
            $disk->delete($this->path);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $generation = Cache::get($this->generationKey());
        $generation = is_array($generation) ? $generation : [];
        $generation['status'] = 'failed';
        $generation['message'] =
            $exception instanceof CsvImportException ? $exception->translationKey : 'top_up_cards.generation_failed';
        $generation['message_replace'] = $exception instanceof CsvImportException ? $exception->replace : [];

        Cache::put($this->generationKey(), $generation, now()->addDay());
        Cache::put($this->batchKey(), ['status' => 'failed'], now()->addDay());
        if (Cache::get('top_up_card_generation:active') === $this->token) {
            Cache::forget('top_up_card_generation:active');
        }
        Storage::disk('local')->delete($this->path);
    }

    /**
     * @return resource
     */
    private function openCsvStream()
    {
        $stream = fopen(Storage::disk('local')->path($this->path), 'rb');

        if (!is_resource($stream)) {
            throw new RuntimeException('The uploaded top-up card CSV file could not be read.');
        }

        $prefix = fread($stream, 3);

        if (str_starts_with((string) $prefix, "\xFF\xFE")) {
            fseek($stream, 2);
            stream_filter_append($stream, 'convert.iconv.UTF-16LE/UTF-8', STREAM_FILTER_READ);
        } elseif (str_starts_with((string) $prefix, "\xFE\xFF")) {
            fseek($stream, 2);
            stream_filter_append($stream, 'convert.iconv.UTF-16BE/UTF-8', STREAM_FILTER_READ);
        } elseif (str_starts_with((string) $prefix, "\xEF\xBB\xBF")) {
            fseek($stream, 3);
        } else {
            fseek($stream, 0);
        }

        return $stream;
    }

    /**
     * @param resource $stream
     * @return array{delimiter: string, indexes: array{serial_no: int, pin: int, amount: int, expires_at: int, status?: int}, headers: list<string>, columns: int}
     */
    private function readHeaders($stream): array
    {
        $firstLine = fgets($stream);

        if ($firstLine === false) {
            throw new CsvImportException('csv.import_errors.invalid_headers');
        }

        $directive = rtrim($firstLine, "\r\n");
        $headerLine = $firstLine;
        $delimiter = $this->detectDelimiter($firstLine);

        if (preg_match('/^sep=(,|;|\t|\|)$/i', $directive, $matches) === 1) {
            $delimiter = $matches[1];
            $headerLine = fgets($stream);

            if ($headerLine === false) {
                throw new CsvImportException('csv.import_errors.invalid_headers');
            }
        }

        $headers = str_getcsv(rtrim($headerLine, "\r\n"), $delimiter);
        $headers = array_map(static function ($header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? '';
            $header = strtolower(trim($header));
            $header = rtrim($header, "* \t");
            $header = str_replace([' ', '-'], '_', $header);

            return match ($header) {
                'expire_at' => 'expires_at',
                default => $header,
            };
        }, $headers);

        if (count($headers) < 4 || count($headers) > 5 || count(array_unique($headers)) !== count($headers)) {
            throw new CsvImportException('csv.import_errors.invalid_headers');
        }

        $indexes = array_flip($headers);
        $required = ['serial_no', 'pin', 'amount', 'expires_at'];

        foreach ($required as $header) {
            if (!array_key_exists($header, $indexes)) {
                throw new CsvImportException('csv.import_errors.invalid_headers');
            }
        }

        if (count($headers) === 5 && !array_key_exists('status', $indexes)) {
            throw new CsvImportException('csv.import_errors.invalid_headers');
        }

        return [
            'delimiter' => $delimiter,
            'indexes' => array_intersect_key($indexes, array_flip([...$required, 'status'])),
            'headers' => array_values($headers),
            'columns' => count($headers),
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param array<int, mixed> $row
     * @return array{serial_no: string, pin: string, amount: int, expires_at: string}
     */
    private function parseRow(array $row, int $line, array $format): array
    {
        if (count($row) !== $format['columns']) {
            throw new CsvImportException('csv.import_errors.invalid_row', ['line' => $line]);
        }

        foreach ($row as $value) {
            $value = trim((string) $value);

            if ($value === '' || strtolower($value) === 'null') {
                throw new CsvImportException('csv.import_errors.invalid_row', ['line' => $line]);
            }
        }

        $serial = trim((string) $row[$format['indexes']['serial_no']]);
        $pin = trim((string) $row[$format['indexes']['pin']]);
        $amount = trim((string) $row[$format['indexes']['amount']]);
        $expiresAt = trim((string) $row[$format['indexes']['expires_at']]);

        if (preg_match('/^\d{16}$/', $serial) !== 1) {
            throw new CsvImportException('csv.import_errors.invalid_serial_no', ['line' => $line]);
        }

        if (preg_match('/^\d{16}$/', $pin) !== 1) {
            throw new CsvImportException('csv.import_errors.invalid_pin', ['line' => $line]);
        }

        $officeCode = TopUpCardOffices::officeCodeFromSerialNo($serial);

        if ($officeCode === null || !$this->hasOfficeCode($officeCode)) {
            throw new CsvImportException('csv.import_errors.invalid_office_code', [
                'line' => $line,
                'office_code' => $officeCode ?? '',
            ]);
        }

        $status = isset($format['indexes']['status']) ? trim((string) $row[$format['indexes']['status']]) : null;

        if ($status !== null && $status !== TopUpCardStatus::Pending->value) {
            throw new CsvImportException('csv.import_errors.invalid_row', ['line' => $line]);
        }

        $parsedExpiry = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt);

        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            strlen($serial) > 32 ||
            filter_var($amount, FILTER_VALIDATE_INT) === false ||
            (int) $amount < 1 ||
            (int) $amount > 1000000 ||
            $parsedExpiry === false ||
            $parsedExpiry->format('Y-m-d') !== $expiresAt ||
            ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            throw new CsvImportException('csv.import_errors.invalid_row', ['line' => $line]);
        }

        return [
            'serial_no' => $serial,
            'pin' => $pin,
            'amount' => (int) $amount,
            'expires_at' => $expiresAt,
        ];
    }

    private function hasOfficeCode(string $officeCode): bool
    {
        if ($this->validOfficeCodes === null) {
            $this->validOfficeCodes = Office::query()
                ->pluck('cd')
                ->mapWithKeys(static fn($code): array => [(string) $code => true])
                ->all();
        }

        return isset($this->validOfficeCodes[$officeCode]);
    }

    /**
     * @param list<array{serial_no: string, pin: string, amount: int, expires_at: string}> $chunk
     */
    private function assertChunkDoesNotExist(array $chunk, int $line, int $chunkIndex): void
    {
        $serials = array_column($chunk, 'serial_no');
        $pinHashes = array_map(static fn(array $row): string => TopUpCardPin::hash($row['pin']), $chunk);
        $existing = DB::table('top_up_card')
            ->whereIn('serial_no', $serials)
            ->orWhereIn('pin', $pinHashes)
            ->get(['serial_no', 'pin']);

        if ($existing->isEmpty()) {
            return;
        }

        $existingBySerial = $existing->keyBy('serial_no');
        $cached = Cache::get($this->generationKey() . ':chunk:' . $chunkIndex);
        $recovering = in_array($cached['status'] ?? null, ['completed', 'inserting'], true);

        if ($recovering && count($existingBySerial) === count($chunk)) {
            foreach ($chunk as $row) {
                $stored = $existingBySerial->get($row['serial_no']);

                if ($stored === null || $stored->pin !== TopUpCardPin::hash($row['pin'])) {
                    $recovering = false;
                    break;
                }
            }

            if ($recovering) {
                return;
            }
        }

        $this->throwForExistingDuplicate($chunk, $line, $existing);
    }

    /**
     * @param list<array{serial_no: string, pin: string, amount: int, expires_at: string}> $chunk
     * @param list<array<string, mixed>> $preview
     * @return list<array<string, mixed>>
     */
    private function insertChunk(array $chunk, int $chunkIndex, array $preview, int $previewLimit, int $batchId): array
    {
        $chunkKey = $this->generationKey() . ':chunk:' . $chunkIndex;
        $cached = Cache::get($chunkKey);
        $cards = array_map(
            static fn(array $row): array => [
                'id' => null,
                'serial_no' => $row['serial_no'],
                'pin' => $row['pin'],
                'amount' => $row['amount'],
                'expires_at' => $row['expires_at'],
                'redeemed_at' => null,
                'redeemed_by' => null,
                'status' => TopUpCardStatus::Active->value,
            ],
            $chunk,
        );

        if (($cached['status'] ?? null) === 'completed') {
            return array_slice([...$preview, ...$cached['cards'] ?? []], 0, $previewLimit);
        }

        $pinHashes = array_map(static fn(array $row): string => TopUpCardPin::hash($row['pin']), $chunk);
        $serials = array_column($chunk, 'serial_no');
        $existing = DB::table('top_up_card')
            ->whereIn('serial_no', $serials)
            ->orWhereIn('pin', $pinHashes)
            ->get(['serial_no', 'pin']);
        $existingBySerial = $existing->keyBy('serial_no');

        $recovering = ($cached['status'] ?? null) === 'inserting';
        $allRowsAlreadyInserted = count($existingBySerial) === count($chunk);

        if ($allRowsAlreadyInserted) {
            foreach ($chunk as $row) {
                $stored = $existingBySerial->get($row['serial_no']);

                if ($stored === null || $stored->pin !== TopUpCardPin::hash($row['pin'])) {
                    $allRowsAlreadyInserted = false;
                    break;
                }
            }
        }

        if ($existing->isNotEmpty() && !($recovering && $allRowsAlreadyInserted)) {
            $this->throwForExistingDuplicate(
                $chunk,
                $chunkIndex * max(1, (int) config('top_up_cards.chunk_size', 1000)) + 2,
                $existing,
            );
        }

        Cache::put($chunkKey, ['status' => 'inserting', 'cards' => $cards], now()->addDay());

        if (!$allRowsAlreadyInserted) {
            if ($batchId < 1) {
                throw new RuntimeException('CSV import batch_id could not be resolved before inserting top-up cards.');
            }

            $now = now();
            $officeIdsBySerial = TopUpCardOffices::resolveIdsBySerialNumbers(array_column($chunk, 'serial_no'));
            $insertRows = array_map(
                static fn(array $row): array => [
                    'serial_no' => $row['serial_no'],
                    'pin' => TopUpCardPin::hash($row['pin']),
                    'amount' => $row['amount'],
                    'expires_at' => $row['expires_at'],
                    'status' => TopUpCardStatus::Active->value,
                    'redeemed_at' => null,
                    'redeemed_by' => null,
                    'office_id' => $officeIdsBySerial[$row['serial_no']] ?? null,
                    'batch_id' => $batchId,
                    'ledger_transaction_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $chunk,
            );

            try {
                DB::transaction(static fn() => DB::table('top_up_card')->insert($insertRows));
            } catch (UniqueConstraintViolationException $exception) {
                $message = strtolower($exception->getMessage());
                $translationKey = str_contains($message, 'pin')
                    ? 'csv.import_errors.duplicate_pin'
                    : 'csv.import_errors.duplicate_serial';

                throw new CsvImportException($translationKey, [
                    'line' => $chunkIndex * max(1, (int) config('top_up_cards.chunk_size', 1000)) + 2,
                ]);
            }
        }

        Cache::put($chunkKey, ['status' => 'completed', 'cards' => $cards], now()->addDay());
        $preview = array_slice([...$preview, ...$cards], 0, $previewLimit);
        $generation = Cache::get($this->generationKey(), []);
        $generation['completed_chunks'] = $chunkIndex + 1;
        $generation['completed_cards'] = min(
            (int) ($generation['total_cards'] ?? 0),
            ($chunkIndex + 1) * max(1, (int) config('top_up_cards.chunk_size', 1000)),
        );
        Cache::put($this->generationKey(), $generation, now()->addDay());

        return $preview;
    }

    /**
     * @param list<array{serial_no: string, pin: string, amount: int, expires_at: string}> $chunk
     */
    private function throwForExistingDuplicate(array $chunk, int $line, Collection $existing): void
    {
        $existingBySerial = $existing->keyBy('serial_no');
        $existingByPin = $existing->keyBy('pin');

        foreach ($chunk as $index => $row) {
            if ($existingBySerial->has($row['serial_no'])) {
                throw new CsvImportException('csv.import_errors.duplicate_serial', ['line' => $line + $index]);
            }

            if ($existingByPin->has(TopUpCardPin::hash($row['pin']))) {
                throw new CsvImportException('csv.import_errors.duplicate_pin', ['line' => $line + $index]);
            }
        }

        throw new CsvImportException('csv.import_errors.duplicate_serial', ['line' => $line]);
    }

    private function generationKey(): string
    {
        return 'top_up_card_generation:' . $this->token;
    }

    private function batchKey(): string
    {
        return $this->generationKey() . ':batch';
    }
}
