<?php

namespace App\Jobs;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Enums\BatchStatus;
use App\Enums\TopUpCardStatus;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\Office;
use App\Support\CsvImportException;
use App\Support\GeneratesTopUpCards;
use App\Support\TopUpCardOffices;
use App\Support\TopUpCardPin;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportGeneratedTopUpCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    /** @var array<string, true>|null */
    private ?array $validOfficeCodes = null;

    public function __construct(
        public readonly string $path,
        public readonly string $token,
        public readonly int $userId,
    ) {
        $retryAfter = (int) config('top_up_cards.retry_after', 600);
        $this->timeout = max(1, min(540, $retryAfter - 60));
        $this->onQueue('top-up-cards');
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return array{denominations: list<int>, rows: list<array{office: string, amount_counts: list<array{amount: int, count: int}>, total_cards: int, generate_date: ?string, activated_at: ?string, expires_at: ?string}>, total_rows: int, total_summaries: int}
     */
    public static function previewCsv(string $path): array
    {
        return (new self($path, 'validation-preview', 0))->readCsvPreview();
    }

    /**
     * @return array{denominations: list<int>, rows: list<array{office: string, amount_counts: list<array{amount: int, count: int}>, total_cards: int, generate_date: ?string, activated_at: ?string, expires_at: ?string}>, total_rows: int, total_summaries: int}
     */
    private function readCsvPreview(): array
    {
        $stream = $this->openCsvStream();
        $totalRows = 0;
        $summaries = [];
        $summaryKeys = [];
        $denominations = [];
        $chunk = [];
        $chunkSize = min(500, max(1, (int) config('top_up_cards.chunk_size', 500)));
        $previewLimit = min(200, max(1, (int) config('top_up_cards.preview_limit', 100)));
        $stagingTable = 'top_up_card_preview_'.Str::lower(Str::random(20));
        Schema::create($stagingTable, static function (Blueprint $table): void {
            $table->string('serial_no', 32)->unique();
            $table->string('pin_hash', 64)->unique();
            $table->temporary();
        });

        try {
            $format = $this->readHeaders($stream);

            while (($row = fgetcsv($stream, 0, $format['delimiter'])) !== false) {
                $line = $totalRows + 2;
                $card = $this->parseRow($row, $line, $format);
                $chunk[] = ['line' => $line, ...$card];
                $totalRows++;
                $denominations[$card['amount']] = true;
                $officeCode = TopUpCardOffices::officeCodeFromSerialNo($card['serial_no']);
                $summaryKey = ($officeCode ?? '').':'.$card['generate_date'];

                if (! isset($summaryKeys[$summaryKey])) {
                    $summaryKeys[$summaryKey] = true;

                    if (count($summaries) < $previewLimit) {
                        $summaries[$summaryKey] = [
                            'office_code' => $officeCode,
                            'amount_counts' => [],
                            'total_cards' => 0,
                            'generate_date' => $card['generate_date'],
                            'activated_at' => null,
                            'expires_at' => null,
                        ];
                    }
                }

                if (isset($summaries[$summaryKey])) {
                    $summaries[$summaryKey]['amount_counts'][$card['amount']] =
                        ($summaries[$summaryKey]['amount_counts'][$card['amount']] ?? 0) + 1;
                    $summaries[$summaryKey]['total_cards']++;
                }

                if ($totalRows > (int) config('top_up_cards.max_cards', 100000)) {
                    throw new CsvImportException('csv.import_errors.too_many_cards', [
                        'max' => (int) config('top_up_cards.max_cards', 100000),
                    ]);
                }

                if (count($chunk) >= $chunkSize) {
                    $this->assertPreviewChunkIsUnique($chunk, $stagingTable);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $this->assertPreviewChunkIsUnique($chunk, $stagingTable);
            }
        } finally {
            fclose($stream);
            Schema::dropIfExists($stagingTable);
        }

        if ($totalRows === 0) {
            throw new CsvImportException('csv.import_errors.no_cards');
        }

        $denominations = array_map('intval', array_keys($denominations));
        sort($denominations, SORT_NUMERIC);
        $totalSummaries = count($summaryKeys);
        $summaries = array_values($summaries);
        $officeCodes = array_values(array_unique(array_filter(array_column($summaries, 'office_code'))));
        $officesByCode = Office::query()
            ->whereIn('cd', $officeCodes)
            ->get(['name_en', 'name_mm', 'name_zh', 'cd'])
            ->keyBy(fn (Office $office): string => (string) $office->cd);
        $rows = array_map(function (array $summary) use ($officesByCode): array {
            $officeCode = $summary['office_code'];
            $office = $officeCode === null ? null : $officesByCode->get($officeCode);
            $amountCounts = [];

            foreach ($summary['amount_counts'] as $amount => $count) {
                $amountCounts[] = ['amount' => (int) $amount, 'count' => $count];
            }
            usort($amountCounts, static fn (array $left, array $right): int => $left['amount'] <=> $right['amount']);

            return [
                'office' => $office?->localizedName() ?? '—',
                'amount_counts' => $amountCounts,
                'total_cards' => $summary['total_cards'],
                'generate_date' => $summary['generate_date'],
                'activated_at' => $summary['activated_at'],
                'expires_at' => $summary['expires_at'],
            ];
        }, array_values($summaries));

        return [
            'denominations' => $denominations,
            'rows' => $rows,
            'total_rows' => $totalRows,
            'total_summaries' => $totalSummaries,
        ];
    }

    /**
     * @param  list<array{line: int, serial_no: string, pin: string, amount: int, generate_date: ?string}>  $chunk
     */
    private function assertPreviewChunkIsUnique(array $chunk, string $stagingTable): void
    {
        $serials = array_column($chunk, 'serial_no');
        $pinHashes = array_map(static fn (array $row): string => TopUpCardPin::hash($row['pin']), $chunk);
        $previousSerials = array_flip(
            DB::table($stagingTable)->whereIn('serial_no', $serials)->pluck('serial_no')->all(),
        );
        $previousPins = array_flip(DB::table($stagingTable)->whereIn('pin_hash', $pinHashes)->pluck('pin_hash')->all());
        $seenSerials = [];
        $seenPins = [];

        foreach ($chunk as $index => $row) {
            $pinHash = $pinHashes[$index];

            if (isset($seenSerials[$row['serial_no']]) || isset($previousSerials[$row['serial_no']])) {
                throw new CsvImportException('csv.import_errors.duplicate_serial', ['line' => $row['line']]);
            }

            if (isset($seenPins[$pinHash]) || isset($previousPins[$pinHash])) {
                throw new CsvImportException('csv.import_errors.duplicate_pin', ['line' => $row['line']]);
            }

            $seenSerials[$row['serial_no']] = true;
            $seenPins[$pinHash] = true;
        }

        DB::table($stagingTable)->insert(
            array_map(
                static fn (array $row, string $pinHash): array => [
                    'serial_no' => $row['serial_no'],
                    'pin_hash' => $pinHash,
                ],
                $chunk,
                $pinHashes,
            ),
        );
    }

    public function handle(): void
    {
        $connection = DB::connection();
        $queryLoggingEnabled = $connection->logging();

        try {
            $this->process();
        } finally {
            if ($queryLoggingEnabled) {
                $connection->enableQueryLog();
            }
        }
    }

    private function process(): void
    {
        $connection = DB::connection();
        $connection->disableQueryLog();
        $connection->flushQueryLog();

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $this->logMemory('import_started');

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
            $metadataItems = [];
            $chunkSize = max(1, (int) config('top_up_cards.chunk_size', 1000));
            $format = $this->readHeaders($stream);

            while (($row = fgetcsv($stream, 0, $format['delimiter'])) !== false) {
                $line = $totalCards + 2;
                $card = $this->parseRow($row, $line, $format);
                $officeCode = TopUpCardOffices::officeCodeFromSerialNo($card['serial_no']);

                if ($officeCode !== null) {
                    $itemKey = $officeCode.':'.$card['amount'];
                    $metadataItems[$itemKey] = ($metadataItems[$itemKey] ?? 0) + 1;
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

        $totalChunks = (int) ceil($totalCards / $chunkSize);
        $this->logMemory('statistics_collected', null, $totalChunks, [
            'metadata_items' => count($metadataItems),
        ]);

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

                    if ($this->shouldLogChunk($validationChunkIndex, $totalChunks)) {
                        $this->logMemory('duplicate_validation_chunk', $validationChunkIndex + 1, $totalChunks);
                    }

                    $validationChunkIndex++;
                }
            }

            if ($validationChunk !== []) {
                $this->assertChunkDoesNotExist(
                    $validationChunk,
                    $validatedRows - count($validationChunk) + 2,
                    $validationChunkIndex,
                );

                if ($this->shouldLogChunk($validationChunkIndex, $totalChunks)) {
                    $this->logMemory('duplicate_validation_chunk', $validationChunkIndex + 1, $totalChunks);
                }
            }
        } finally {
            fclose($stream);
        }

        $this->logMemory('duplicate_validation_completed', null, $totalChunks);

        try {
            $stream = $this->openCsvStream();
            $format = $this->readHeaders($stream);
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
                    $metadataItems,
                    $chunkSize,
                    $previewLimit,
                ): void {
                    $batchId = (int) (Cache::get($this->batchKey())['batch_id'] ?? 0);
                    $importBatch = $batchId > 0 ? Batch::query()->find($batchId) : null;

                    if (! ($importBatch instanceof Batch)) {
                        $importBatch = Batch::query()->create([
                            'batch_no' => GeneratesTopUpCards::nextBatchNo(),
                            'status' => BatchStatus::Pending,
                            'expires_at' => null,
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
                            'expires_at' => null,
                            'activated_at' => null,
                            'activation_expires_at' => null,
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
                            if ($this->shouldLogChunk($chunkIndex, $totalChunks)) {
                                $this->logMemory('insert_chunk_before', $chunkIndex + 1, $totalChunks);
                            }

                            $preview = $this->insertChunk(
                                $chunk,
                                $chunkIndex,
                                $preview,
                                $previewLimit,
                                (int) $importBatch->id,
                            );
                            $chunk = [];

                            if ($this->shouldLogChunk($chunkIndex, $totalChunks)) {
                                $this->logMemory('insert_chunk_after', $chunkIndex + 1, $totalChunks);
                            }

                            $chunkIndex++;
                        }
                    }

                    if ($chunk !== []) {
                        if ($this->shouldLogChunk($chunkIndex, $totalChunks)) {
                            $this->logMemory('insert_chunk_before', $chunkIndex + 1, $totalChunks);
                        }

                        $preview = $this->insertChunk(
                            $chunk,
                            $chunkIndex,
                            $preview,
                            $previewLimit,
                            (int) $importBatch->id,
                        );
                        $chunkIndex++;
                        $this->logMemory('insert_chunk_after', $chunkIndex, $totalChunks);
                    }

                    $batchItems = [];

                    foreach ($metadataItems as $itemKey => $quantity) {
                        [$officeCode, $amount] = explode(':', (string) $itemKey, 2);
                        $batchItems[] = [
                            'office_cd' => $officeCode,
                            'amount' => (int) $amount,
                            'quantity' => $quantity,
                        ];
                    }

                    Batch::query()
                        ->whereKey($importBatch->id)
                        ->update([
                            'total_value' => $totalValue,
                            'quantity' => $totalCards,
                            'status' => BatchStatus::Pending,
                            'expires_at' => null,
                            'metadata' => [
                                'import_status' => 'completed',
                                'total_cards' => $totalCards,
                                'total_value' => (string) $totalValue,
                                'items' => $batchItems,
                                'user_id' => $this->userId,
                            ],
                        ]);
                    unset($batchItems);
                });
            } catch (Throwable $exception) {
                for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
                    Cache::forget($this->generationKey().':chunk:'.$chunkIndex);
                }

                $generation = Cache::get($this->generationKey(), []);

                if (is_array($generation)) {
                    $generation['status'] = 'processing';
                    $generation['completed_cards'] = 0;
                    $generation['completed_chunks'] = 0;
                    Cache::put($this->generationKey(), $generation, now()->addDay());
                }

                $this->logMemory('transaction_rolled_back', null, $totalChunks);

                throw $exception;
            }

            $this->logMemory('database_committed', null, $totalChunks);

            if (! ($importBatch instanceof Batch)) {
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
                    'expires_at' => null,
                    'activated_at' => null,
                    'activation_expires_at' => null,
                    'user_id' => $this->userId,
                    'source' => 'csv_import',
                    'preview_payload' => Crypt::encryptString(json_encode($preview, JSON_THROW_ON_ERROR)),
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
            $this->logMemory('import_completed', null, $totalChunks);
            $this->logImportSucceeded($importBatch, $totalCards, $totalValue);
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
        $batchId = (int) (Cache::get($this->batchKey())['batch_id'] ?? 0);
        $importBatch = $batchId > 0 ? Batch::query()->find($batchId) : null;

        if (
            $importBatch instanceof Batch &&
            ($importBatch->metadata['import_status'] ?? null) === 'completed' &&
            (int) DB::table('top_up_card')->where('batch_id', $importBatch->id)->count() ===
                (int) ($importBatch->metadata['total_cards'] ?? -1)
        ) {
            $generation['status'] = 'completed';
            $generation['completed_cards'] = (int) $importBatch->metadata['total_cards'];
            $generation['completed_chunks'] = (int) ($generation['total_chunks'] ?? 0);
            Cache::put($this->generationKey(), $generation, now()->addDay());
            Cache::put($this->batchKey(), ['status' => 'completed', 'batch_id' => $importBatch->id], now()->addDay());
            if (Cache::get('top_up_card_generation:active') === $this->token) {
                Cache::forget('top_up_card_generation:active');
            }
            Storage::disk('local')->delete($this->path);
            $this->logImportSucceeded(
                $importBatch,
                (int) ($importBatch->metadata['total_cards'] ?? 0),
                (int) ($importBatch->metadata['total_value'] ?? 0),
            );

            return;
        }

        $generation['status'] = 'failed';
        $generation['message'] =
            $exception instanceof CsvImportException ? $exception->translationKey : 'top_up_cards.generation_failed';
        $generation['message_replace'] = $exception instanceof CsvImportException ? $exception->replace : [];
        $totalCards = max(0, (int) ($generation['total_cards'] ?? 0));
        $completedCards =
            $importBatch instanceof Batch
                ? min($totalCards, (int) DB::table('top_up_card')->where('batch_id', $importBatch->id)->count())
                : 0;
        $chunkSize = max(1, (int) config('top_up_cards.chunk_size', 1000));
        $generation['completed_cards'] = $completedCards;
        $generation['completed_chunks'] = min(
            max(0, (int) ($generation['total_chunks'] ?? 0)),
            (int) ceil($completedCards / $chunkSize),
        );

        Cache::put($this->generationKey(), $generation, now()->addDay());
        Cache::put($this->batchKey(), ['status' => 'failed'], now()->addDay());
        for ($chunkIndex = 0; $chunkIndex < (int) ($generation['total_chunks'] ?? 0); $chunkIndex++) {
            Cache::forget($this->generationKey().':chunk:'.$chunkIndex);
        }

        if (Cache::get('top_up_card_generation:active') === $this->token) {
            Cache::forget('top_up_card_generation:active');
        }
        Storage::disk('local')->delete($this->path);
        $this->logImportFailed($exception, $importBatch, $generation);
    }

    private function logImportSucceeded(Batch $batch, int $totalCards, int|string $totalValue): void
    {
        activity(ActivityLogName::TopUpCards->value)
            ->causedBy(Admin::query()->find($this->userId))
            ->performedOn($batch)
            ->event(ActivityLogEvent::Succeeded->value)
            ->withProperties([
                'batch_no' => $batch->batch_no,
                'quantity' => $totalCards,
                'total_value' => (string) $totalValue,
            ])
            ->log('top_up_cards_batch_import_succeeded');
    }

    /**
     * @param  array<string, mixed>  $generation
     */
    private function logImportFailed(Throwable $exception, ?Batch $batch, array $generation): void
    {
        $activity = activity(ActivityLogName::TopUpCards->value)
            ->causedBy(Admin::query()->find($this->userId))
            ->event(ActivityLogEvent::Failed->value)
            ->withProperties([
                'batch_no' => $batch?->batch_no,
                'quantity' => (int) ($generation['total_cards'] ?? 0),
                'completed_cards' => (int) ($generation['completed_cards'] ?? 0),
                'error' => $exception instanceof CsvImportException
                        ? $exception->translationKey
                        : 'top_up_cards.generation_failed',
            ]);

        if ($batch instanceof Batch) {
            $activity->performedOn($batch);
        }

        $activity->log('top_up_cards_batch_import_failed');
    }

    /**
     * @return resource
     */
    private function openCsvStream()
    {
        $stream = Storage::disk('local')->readStream($this->path);

        if (! is_resource($stream)) {
            Log::warning('Unable to open uploaded top-up card CSV from local storage.', [
                'path' => $this->path,
                'exists' => Storage::disk('local')->exists($this->path),
            ]);

            throw new CsvImportException('csv.import_errors.read_failed');
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
     * @param  resource  $stream
     * @return array{delimiter: string, indexes: array{serial_no: int, pin: int, amount: int, generate_date: int}, headers: list<string>, columns: int}
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
                'serialnumber', 'serial_number' => 'serial_no',
                'pin_code' => 'pin',
                'pointvalue', 'point_value' => 'amount',
                'generatedate' => 'generate_date',
                default => $header,
            };
        }, $headers);

        $allowedHeaders = ['serial_no', 'pin', 'amount', 'generate_date'];
        $required = ['serial_no', 'pin', 'amount', 'generate_date'];

        if (
            count($headers) !== 4 ||
            count(array_unique($headers)) !== count($headers) ||
            array_diff($headers, $allowedHeaders) !== []
        ) {
            throw new CsvImportException('csv.import_errors.invalid_headers');
        }

        $indexes = array_flip($headers);

        foreach ($required as $header) {
            if (! array_key_exists($header, $indexes)) {
                throw new CsvImportException('csv.import_errors.invalid_headers');
            }
        }

        return [
            'delimiter' => $delimiter,
            'indexes' => array_intersect_key($indexes, array_flip($required)),
            'headers' => array_values($headers),
            'columns' => count($headers),
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            '|' => substr_count($line, '|'),
        ];
        arsort($counts);

        $delimiter = (string) array_key_first($counts);

        return $counts[$delimiter] > 0 ? $delimiter : ' ';
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array{serial_no: string, pin: string, amount: int, generate_date: ?string}
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
        $pin = str_replace('-', '', trim((string) $row[$format['indexes']['pin']]));
        $amount = trim((string) $row[$format['indexes']['amount']]);
        $generateDate = trim((string) $row[$format['indexes']['generate_date']]);

        if (preg_match('/^\d{16}$/', $serial) !== 1) {
            throw new CsvImportException('csv.import_errors.invalid_serial_no', ['line' => $line]);
        }

        if (preg_match('/^\d{16}$/', $pin) !== 1) {
            throw new CsvImportException('csv.import_errors.invalid_pin', ['line' => $line]);
        }

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $generateDate);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            strlen($serial) > 32 ||
            filter_var($amount, FILTER_VALIDATE_INT) === false ||
            ! is_numeric($amount) ||
            (float) $amount < 1 ||
            (float) $amount > 1000000 ||
            $parsedDate === false ||
            $parsedDate->format('Y-m-d') !== $generateDate ||
            ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            throw new CsvImportException('csv.import_errors.invalid_row', ['line' => $line]);
        }

        $officeCode = TopUpCardOffices::officeCodeFromSerialNo($serial);

        if ($officeCode === null || ! $this->hasOfficeCode($officeCode)) {
            throw new CsvImportException('csv.import_errors.invalid_office_code', [
                'line' => $line,
                'office_code' => $officeCode ?? '',
            ]);
        }

        return [
            'serial_no' => $serial,
            'pin' => $pin,
            'amount' => round((float) $amount, 2),
            'generate_date' => $generateDate,
        ];
    }

    private function hasOfficeCode(string $officeCode): bool
    {
        if ($this->validOfficeCodes === null) {
            $this->validOfficeCodes = Office::query()
                ->pluck('cd')
                ->mapWithKeys(static fn ($code): array => [(string) $code => true])
                ->all();
        }

        return isset($this->validOfficeCodes[$officeCode]);
    }

    /**
     * @param  list<array{serial_no: string, pin: string, amount: int, generate_date: ?string}>  $chunk
     */
    private function assertChunkDoesNotExist(array $chunk, int $line, int $chunkIndex): void
    {
        $serials = [];
        $pinHashes = [];

        foreach ($chunk as $row) {
            $serials[] = $row['serial_no'];
            $pinHashes[] = TopUpCardPin::hash($row['pin']);
        }

        ['by_serial' => $existingBySerial, 'by_pin' => $existingByPin] = $this->existingCardMaps($serials, $pinHashes);

        if ($existingBySerial === [] && $existingByPin === []) {
            return;
        }

        $cached = Cache::get($this->generationKey().':chunk:'.$chunkIndex);
        $generation = Cache::get($this->generationKey(), []);
        $cachedBatch = Cache::get($this->batchKey());
        $batchId = (int) ($generation['batch_id'] ?? (is_array($cachedBatch) ? $cachedBatch['batch_id'] ?? 0 : 0));
        $recovering =
            $batchId > 0 &&
            in_array($cached['status'] ?? null, ['completed', 'inserting'], true) &&
            count($existingBySerial) === count($chunk);

        if ($recovering) {
            foreach ($chunk as $index => $row) {
                $stored = $existingBySerial[$row['serial_no']] ?? null;

                if (
                    $stored === null ||
                    (int) $stored->batch_id !== $batchId ||
                    $stored->pin !== $pinHashes[$index]
                ) {
                    $recovering = false;
                    break;
                }
            }

            if ($recovering) {
                return;
            }
        }

        $this->throwForExistingDuplicate($chunk, $line, $existingBySerial, $existingByPin, $pinHashes);
    }

    /**
     * @param  list<array{serial_no: string, pin: string, amount: int, generate_date: ?string}>  $chunk
     * @param  list<array<string, mixed>>  $preview
     * @return list<array<string, mixed>>
     */
    private function insertChunk(array $chunk, int $chunkIndex, array $preview, int $previewLimit, int $batchId): array
    {
        $chunkKey = $this->generationKey().':chunk:'.$chunkIndex;
        $cached = Cache::get($chunkKey);
        $serials = [];
        $pinHashes = [];
        $previewCards = [];
        $exportPayloadJson = '[';

        foreach ($chunk as $index => $row) {
            $serials[] = $row['serial_no'];
            $pinHashes[] = TopUpCardPin::hash($row['pin']);
            $exportPayloadJson .= ($index > 0 ? ',' : '').json_encode(
                [$row['serial_no'], $row['pin'], $row['amount'], $row['generate_date']],
                JSON_THROW_ON_ERROR,
            );

            if (count($preview) + count($previewCards) < $previewLimit) {
                $previewCards[] = [
                    'id' => null,
                    'serial_no' => $row['serial_no'],
                    'pin' => $row['pin'],
                    'amount' => $row['amount'],
                    'activated_at' => null,
                    'expires_at' => null,
                    'redeemed_at' => null,
                    'redeemed_by' => null,
                    'status' => TopUpCardStatus::Pending->value,
                ];
            }
        }

        $exportPayloadJson .= ']';
        ['by_serial' => $existingBySerial, 'by_pin' => $existingByPin] = $this->existingCardMaps($serials, $pinHashes);

        $recovering = in_array($cached['status'] ?? null, ['completed', 'inserting'], true);
        $allRowsAlreadyInserted = count($existingBySerial) === count($chunk);

        if ($allRowsAlreadyInserted) {
            foreach ($chunk as $index => $row) {
                $stored = $existingBySerial[$row['serial_no']] ?? null;

                if (
                    $stored === null ||
                    (int) $stored->batch_id !== $batchId ||
                    $stored->pin !== $pinHashes[$index]
                ) {
                    $allRowsAlreadyInserted = false;
                    break;
                }
            }
        }

        if (($existingBySerial !== [] || $existingByPin !== []) && ! ($recovering && $allRowsAlreadyInserted)) {
            $this->throwForExistingDuplicate(
                $chunk,
                $chunkIndex * max(1, (int) config('top_up_cards.chunk_size', 1000)) + 2,
                $existingBySerial,
                $existingByPin,
                $pinHashes,
            );
        }

        $chunkPayload = [
            'preview_payload' => Crypt::encryptString(json_encode($previewCards, JSON_THROW_ON_ERROR)),
            'export_payload' => Crypt::encryptString($exportPayloadJson),
        ];
        unset($exportPayloadJson);
        Cache::put($chunkKey, ['status' => 'inserting'] + $chunkPayload, now()->addDay());

        if (! $allRowsAlreadyInserted) {
            if ($batchId < 1) {
                throw new RuntimeException('CSV import batch_id could not be resolved before inserting top-up cards.');
            }

            $now = now();
            $officeIdsBySerial = TopUpCardOffices::resolveIdsBySerialNumbers($serials);
            $insertRows = [];

            foreach ($chunk as $index => $row) {
                $insertRows[] = [
                    'serial_no' => $row['serial_no'],
                    'pin' => $pinHashes[$index],
                    'amount' => $row['amount'],
                    'generate_date' => $row['generate_date'] ?? $now->toDateString(),
                    'activated_at' => null,
                    'expires_at' => null,
                    'status' => TopUpCardStatus::Pending->value,
                    'redeemed_at' => null,
                    'redeemed_by' => null,
                    'office_id' => $officeIdsBySerial[$row['serial_no']] ?? null,
                    'batch_id' => $batchId,
                    'ledger_transaction_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            try {
                DB::table('top_up_card')->insert($insertRows);
            } catch (UniqueConstraintViolationException $exception) {
                $message = strtolower($exception->getMessage());
                $translationKey = str_contains($message, 'pin')
                    ? 'csv.import_errors.duplicate_pin'
                    : 'csv.import_errors.duplicate_serial';

                throw new CsvImportException($translationKey, [
                    'line' => $chunkIndex * max(1, (int) config('top_up_cards.chunk_size', 1000)) + 2,
                ]);
            }

            unset($officeIdsBySerial, $insertRows);
        }

        Cache::put($chunkKey, ['status' => 'completed'] + $chunkPayload, now()->addDay());

        foreach ($previewCards as $card) {
            $preview[] = $card;
        }

        $generation = Cache::get($this->generationKey(), []);
        $generation['completed_chunks'] = $chunkIndex + 1;
        $generation['completed_cards'] = min(
            (int) ($generation['total_cards'] ?? 0),
            ($chunkIndex + 1) * max(1, (int) config('top_up_cards.chunk_size', 1000)),
        );
        Cache::put($this->generationKey(), $generation, now()->addDay());
        unset($chunkPayload, $previewCards, $pinHashes, $serials, $existingBySerial, $existingByPin);

        return $preview;
    }

    /**
     * @param  list<array{serial_no: string, pin: string, amount: int, generate_date: ?string}>  $chunk
     * @param  array<string, object>  $existingBySerial
     * @param  array<string, true>  $existingByPin
     * @param  list<string>  $pinHashes
     */
    private function throwForExistingDuplicate(
        array $chunk,
        int $line,
        array $existingBySerial,
        array $existingByPin,
        array $pinHashes,
    ): void {
        foreach ($chunk as $index => $row) {
            if (isset($existingBySerial[$row['serial_no']])) {
                throw new CsvImportException('csv.import_errors.serial_already_exists', ['line' => $line + $index]);
            }

            if (isset($existingByPin[$pinHashes[$index]])) {
                throw new CsvImportException('csv.import_errors.duplicate_pin', ['line' => $line + $index]);
            }
        }

        throw new CsvImportException('csv.import_errors.duplicate_serial', ['line' => $line]);
    }

    /**
     * @param  list<string>  $serials
     * @param  list<string>  $pinHashes
     * @return array{by_serial: array<string, object>, by_pin: array<string, true>}
     */
    private function existingCardMaps(array $serials, array $pinHashes): array
    {
        $bySerial = [];
        $byPin = [];

        foreach (
            DB::table('top_up_card')
                ->whereIn('serial_no', $serials)
                ->orWhereIn('pin', $pinHashes)
                ->select(['serial_no', 'pin', 'batch_id'])
                ->cursor() as $record
        ) {
            $bySerial[$record->serial_no] = $record;
            $byPin[$record->pin] = true;
        }

        return ['by_serial' => $bySerial, 'by_pin' => $byPin];
    }

    private function shouldLogChunk(int $chunkIndex, int $totalChunks): bool
    {
        return $chunkIndex === 0 || $chunkIndex === $totalChunks - 1 || ($chunkIndex + 1) % 20 === 0;
    }

    /**
     * @param  array<string, int>  $context
     */
    private function logMemory(
        string $stage,
        ?int $chunkIndex = null,
        ?int $totalChunks = null,
        array $context = [],
    ): void {
        $memoryUnit = 1024 * 1024;
        $memoryLimit = ini_get('memory_limit');

        Log::info('Top-up card CSV import memory usage', array_merge([
            'stage' => $stage,
            'memory_mb' => round(memory_get_usage(false) / $memoryUnit, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(false) / $memoryUnit, 2),
            'allocated_memory_mb' => round(memory_get_usage(true) / $memoryUnit, 2),
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'php_memory_limit' => is_string($memoryLimit) ? $memoryLimit : 'unknown',
        ], $context));
    }

    private function generationKey(): string
    {
        return 'top_up_card_generation:'.$this->token;
    }

    private function batchKey(): string
    {
        return $this->generationKey().':batch';
    }
}
