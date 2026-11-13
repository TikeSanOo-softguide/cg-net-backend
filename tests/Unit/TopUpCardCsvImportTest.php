<?php

namespace Tests\Unit;

use App\Enums\TopUpCardStatus;
use App\Jobs\ImportGeneratedTopUpCardsJob;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Support\CsvImportException;
use App\Support\TopUpCardPin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TopUpCardCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_job_uses_redis_retry_after_for_timeout(): void
    {
        config([
            'top_up_cards.retry_after' => 600,
        ]);
        $job = new ImportGeneratedTopUpCardsJob('imports/cards.csv', 'timeout-test', 1);

        $this->assertSame(540, $job->timeout);
        $this->assertNull($job->connection);
        $this->assertSame('top-up-cards', $job->queue);
    }

    public function test_preview_rejects_the_removed_status_column(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,generate_date,status\n2609111128316006,1234567890123456,1000,2026-11-03,active\n",
            'csv.import_errors.invalid_headers',
            [],
        );
    }

    public function test_preview_rejects_the_unneeded_expires_at_column(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,generate_date,expires_at\n2609111128316006,1234567890123456,1000,2026-11-03,2030-12-31\n",
            'csv.import_errors.invalid_headers',
            [],
        );
    }

    public function test_preview_returns_validated_rows_without_pin_values(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->startOfDay());
        Storage::fake('local');
        $this->makeOffice('Office 31', 'Main Street', '31');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "serial_no,pin,amount,generate_date\n2609111128316006,1234567890123456,1000,2026-11-03\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame(
            [
                [
                    'office' => 'Office 31',
                    'amount_counts' => [['amount' => 1000, 'count' => 1]],
                    'total_cards' => 1,
                    'generate_date' => '2026-11-03',
                    'activated_at' => null,
                    'expires_at' => null,
                ],
            ],
            $preview['rows'],
        );
        $this->assertSame([1000], $preview['denominations']);
        $this->assertSame(1, $preview['total_summaries']);
    }

    public function test_preview_discovers_all_csv_denominations_and_counts_cards(): void
    {
        Storage::fake('local');
        $this->makeOffice('Office 31', 'Main Street', '31');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            implode("\n", [
                'serial_no,pin,amount,generate_date',
                '2609111128316006,1234567890123456,5,2030-12-31',
                '2609111128316007,2234567890123456,10,2030-12-31',
                '2609111128316008,3234567890123456,15,2030-12-31',
                '2609111128316009,4234567890123456,5,2030-12-31',
            ]),
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertSame([5, 10, 15], $preview['denominations']);
        $this->assertSame(
            [
                ['amount' => 5, 'count' => 2],
                ['amount' => 10, 'count' => 1],
                ['amount' => 15, 'count' => 1],
            ],
            $preview['rows'][0]['amount_counts'],
        );
        $this->assertSame(4, $preview['rows'][0]['total_cards']);
    }

    public function test_preview_limits_stored_summary_rows_but_counts_all_summaries(): void
    {
        config([
            'top_up_cards.chunk_size' => 1,
            'top_up_cards.preview_limit' => 1,
        ]);
        Storage::fake('local');
        $this->makeOffice('Office 31', 'Main Street', '31');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            implode("\n", [
                'serial_no,pin,amount,generate_date',
                '2609111128316006,1234567890123456,1000,2026-11-03',
                '2609111128316007,2234567890123456,1000,2026-11-03',
                '2609111128316008,3234567890123456,1000,2026-11-04',
            ]),
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertCount(1, $preview['rows']);
        $this->assertSame(2, $preview['total_summaries']);
        $this->assertSame(2, $preview['rows'][0]['total_cards']);
    }

    public function test_csv_import_handles_100000_cards_with_compact_encrypted_chunk_cache(): void
    {
        config([
            'top_up_cards.chunk_size' => 500,
            'top_up_cards.preview_limit' => 100,
            'top_up_cards.max_cards' => 100000,
        ]);
        Storage::fake('local');
        $this->makeOffice('Office 31', 'Main Street', '31');
        $path = 'imports/top-up-cards/large.csv';
        Storage::disk('local')->put($path, "serial_no,pin,amount,generate_date\n");
        $stream = fopen(Storage::disk('local')->path($path), 'ab');
        $this->assertIsResource($stream);

        for ($index = 1; $index <= 100000; $index++) {
            $serial = str_pad((string) $index, 10, '0', STR_PAD_LEFT).'31'.'0001';
            $pin = str_pad((string) $index, 16, '0', STR_PAD_LEFT);
            fwrite($stream, "{$serial},{$pin},{$index},2026-11-03\n");
        }

        fclose($stream);

        (new ImportGeneratedTopUpCardsJob($path, 'large-import', 0))->handle();

        $generation = Cache::get('top_up_card_generation:large-import');
        $chunk = Cache::get('top_up_card_generation:large-import:chunk:0');

        $this->assertSame('completed', $generation['status']);
        $this->assertSame(100000, $generation['completed_cards']);
        $this->assertSame(100000, TopUpCard::query()->count());
        $this->assertArrayNotHasKey('cards', $generation);
        $this->assertArrayNotHasKey('cards', $chunk);
        $this->assertStringNotContainsString('0000000000000001', $chunk['export_payload']);
        $this->assertCount(
            100,
            json_decode(Crypt::decryptString($generation['preview_payload']), true, 512, JSON_THROW_ON_ERROR),
        );
        $exportRows = json_decode(Crypt::decryptString($chunk['export_payload']), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(500, $exportRows);
        $this->assertSame('0000000000000001', $exportRows[0][1]);
    }

    public function test_csv_import_keeps_cards_pending_until_batch_activation(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->startOfDay());
        Storage::fake('local');
        $this->makeOffice('Office 88', 'Main Street', '88');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "SerialNumber,PIN_Code,PointValue,GenerateDate\n2610110123881001,1341-8663-1662-1366,50,2026-10-23\n",
        );
        $path = $file->store('imports/top-up-cards', 'local');
        $job = new ImportGeneratedTopUpCardsJob($path, 'formatted-pin-import', 0);

        $job->handle();

        $card = TopUpCard::query()->where('serial_no', '2610110123881001')->firstOrFail();
        $this->assertSame(TopUpCardPin::hash('1341866316621366'), $card->pin);
        $this->assertSame('2026-10-23', $card->generate_date->format('Y-m-d'));
        $this->assertNull($card->activated_at);
        $this->assertNull($card->expires_at);
        $this->assertSame('pending', $card->status->value);
        $this->assertSame('50.00', $card->amount);
        $this->assertSame('pending', $card->batch->status->value);
        $this->assertNull($card->batch->expires_at);
    }

    public function test_csv_import_rolls_back_the_batch_and_all_cards_if_a_chunk_insert_fails(): void
    {
        config(['top_up_cards.chunk_size' => 1]);
        Storage::fake('local');
        $this->makeOffice('Office 31', 'Main Street', '31');
        DB::statement(<<<'SQL'
            CREATE TRIGGER reject_second_imported_card
            BEFORE INSERT ON top_up_card
            WHEN NEW.serial_no = '2609111128316007'
            BEGIN
                SELECT RAISE(ABORT, 'forced import failure');
            END
            SQL);
        $path = UploadedFile::fake()->createWithContent(
            'cards.csv',
            implode("\n", [
                'serial_no,pin,amount,generate_date',
                '2609111128316006,1234567890123456,1000,2026-11-03',
                '2609111128316007,2234567890123456,1000,2026-11-03',
            ]),
        )->store('imports/top-up-cards', 'local');

        $job = new ImportGeneratedTopUpCardsJob($path, 'atomic-import', 0);

        try {
            $job->handle();
            $this->fail('The second card insert should fail.');
        } catch (QueryException $exception) {
            $job->failed($exception);

            $this->assertSame(0, TopUpCard::query()->count());
            $this->assertSame(0, Batch::query()->count());
            $this->assertSame(0, Cache::get('top_up_card_generation:atomic-import')['completed_cards']);
            $this->assertSame(0, Cache::get('top_up_card_generation:atomic-import')['completed_chunks']);
            $this->assertNull(Cache::get('top_up_card_generation:atomic-import:chunk:0'));
            $this->assertNull(Cache::get('top_up_card_generation:atomic-import:chunk:1'));
        }
    }

    public function test_csv_import_resumes_after_completed_chunks_from_an_interrupted_attempt(): void
    {
        config(['top_up_cards.chunk_size' => 1]);
        Storage::fake('local');
        $office = $this->makeOffice('Office 31', 'Main Street', '31');
        $batch = Batch::factory()->create([
            'status' => 'pending',
            'expires_at' => null,
        ]);
        $firstSerial = '2609111128316006';
        $firstPin = '1234567890123456';
        TopUpCard::factory()->create([
            'serial_no' => $firstSerial,
            'pin' => TopUpCardPin::hash($firstPin),
            'amount' => 1000,
            'office_id' => $office->id,
            'batch_id' => $batch->id,
            'status' => TopUpCardStatus::Pending,
            'activated_at' => null,
            'expires_at' => null,
        ]);

        $path = UploadedFile::fake()->createWithContent(
            'cards.csv',
            implode("\n", [
                'serial_no,pin,amount,generate_date',
                "{$firstSerial},{$firstPin},1000,2026-11-03",
                '2609111128316007,2234567890123456,1000,2026-11-03',
            ]),
        )->store('imports/top-up-cards', 'local');
        $token = 'interrupted-import';
        Cache::put('top_up_card_generation:'.$token, [
            'status' => 'processing',
            'source' => 'csv_import',
            'total_cards' => 2,
            'total_value' => '2000',
            'completed_cards' => 1,
            'total_chunks' => 2,
            'completed_chunks' => 1,
            'validated' => true,
            'import_summary' => [
                'generate_date' => '2026-11-03',
                'total_cards' => 2,
                'total_value' => 2000,
                'amounts' => [['amount' => 1000, 'cards' => 2, 'value' => 2000]],
                'metadata_items' => [],
            ],
        ], now()->addDay());
        Cache::put('top_up_card_generation:'.$token.':batch', [
            'status' => 'processing',
            'batch_id' => $batch->id,
        ], now()->addDay());
        Cache::put('top_up_card_generation:'.$token.':chunk:0', [
            'status' => 'completed',
            'cards' => [[
                'serial_no' => $firstSerial,
                'pin' => $firstPin,
                'amount' => 1000,
                'activated_at' => null,
                'expires_at' => null,
                'redeemed_at' => null,
                'redeemed_by' => null,
                'status' => TopUpCardStatus::Pending->value,
            ]],
        ], now()->addDay());

        (new ImportGeneratedTopUpCardsJob($path, $token, 0))->handle();

        $this->assertSame(2, TopUpCard::query()->where('batch_id', $batch->id)->count());
        $this->assertSame(TopUpCardStatus::Pending, TopUpCard::query()
            ->where('serial_no', '2609111128316007')
            ->firstOrFail()
            ->status);
        $this->assertSame('completed', Cache::get('top_up_card_generation:'.$token)['status']);
    }

    public function test_csv_import_does_not_resume_a_cached_chunk_with_a_different_pin(): void
    {
        config(['top_up_cards.chunk_size' => 1]);
        Storage::fake('local');
        $office = $this->makeOffice('Office 31', 'Main Street', '31');
        $batch = Batch::factory()->create([
            'status' => 'pending',
            'expires_at' => null,
        ]);
        $serial = '2609111128316006';
        $csvPin = '2234567890123456';
        TopUpCard::factory()->create([
            'serial_no' => $serial,
            'pin' => TopUpCardPin::hash('1234567890123456'),
            'amount' => 1000,
            'office_id' => $office->id,
            'batch_id' => $batch->id,
            'status' => TopUpCardStatus::Pending,
            'activated_at' => null,
            'expires_at' => null,
        ]);
        $path = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "serial_no,pin,amount,generate_date\n{$serial},{$csvPin},1000,2026-11-03\n",
        )->store('imports/top-up-cards', 'local');
        $token = 'mismatched-recovered-chunk';
        Cache::put('top_up_card_generation:'.$token, [
            'status' => 'processing',
            'source' => 'csv_import',
            'total_cards' => 1,
            'total_value' => '1000',
            'completed_cards' => 1,
            'total_chunks' => 1,
            'completed_chunks' => 1,
            'validated' => true,
            'import_summary' => [
                'generate_date' => '2026-11-03',
                'total_cards' => 1,
                'total_value' => 1000,
                'amounts' => [['amount' => 1000, 'cards' => 1, 'value' => 1000]],
                'metadata_items' => [],
            ],
        ], now()->addDay());
        Cache::put('top_up_card_generation:'.$token.':batch', [
            'status' => 'processing',
            'batch_id' => $batch->id,
        ], now()->addDay());
        Cache::put('top_up_card_generation:'.$token.':chunk:0', [
            'status' => 'completed',
            'cards' => [[
                'serial_no' => $serial,
                'pin' => $csvPin,
                'amount' => 1000,
                'activated_at' => null,
                'expires_at' => null,
                'redeemed_at' => null,
                'redeemed_by' => null,
                'status' => TopUpCardStatus::Pending->value,
            ]],
        ], now()->addDay());

        try {
            (new ImportGeneratedTopUpCardsJob($path, $token, 0))->handle();
            $this->fail('A cached chunk with a different stored PIN must not be accepted.');
        } catch (CsvImportException $exception) {
            $this->assertSame('csv.import_errors.serial_already_exists', $exception->translationKey);
            $this->assertSame(1, TopUpCard::query()->where('batch_id', $batch->id)->count());
            $this->assertNotSame('completed', Cache::get('top_up_card_generation:'.$token)['status']);
        }
    }

    public function test_preview_accepts_generated_card_csv_without_expire_date_column(): void
    {
        Storage::fake('local');
        $this->makeOffice('Office 88', 'Main Street', '88');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "SerialNumber,PIN_Code,PointValue,GenerateDate\n2610110123881001,1341-8663-1662-1366,50,2026-10-23\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame('2026-10-23', $preview['rows'][0]['generate_date']);
    }

    public function test_preview_accepts_spaced_headers_and_pipe_delimited_csv(): void
    {
        Storage::fake('local');
        $this->makeOffice('Office 88', 'Main Street', '88');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "Serial Number|PIN Code|Point Value|Generate Date\n2610110123881001|1341-8663-1662-1366|50|2026-10-23\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame('2026-10-23', $preview['rows'][0]['generate_date']);
    }

    public function test_preview_accepts_space_delimited_csv_with_generate_date(): void
    {
        Storage::fake('local');
        $this->makeOffice('Office 88', 'Main Street', '88');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "SerialNumber PIN_Code PointValue GenerateDate\n2610110123881001 1341-8663-1662-1366 50 2026-10-23\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame('2026-10-23', $preview['rows'][0]['generate_date']);
        $this->assertSame([50], $preview['denominations']);
        $this->assertSame([['amount' => 50, 'count' => 1]], $preview['rows'][0]['amount_counts']);
    }

    public function test_preview_rejects_timestamp_generate_date_values(): void
    {
        $this->assertPreviewError(
            "SerialNumber,PIN_Code,PointValue,GenerateDate\n2610110123881001,1341-8663-1662-1366,50,2026-10-23 14:15:00\n",
            'csv.import_errors.invalid_row',
            ['line' => 2],
        );
    }

    public function test_preview_rejects_an_unknown_csv_column(): void
    {
        $this->assertPreviewError(
            "SerialNumber,PIN_Code,PointValue,GenerateDate,UnknownDate\n2610110123881001,1341-8663-1662-1366,50,2026-10-23,2028-10-23\n",
            'csv.import_errors.invalid_headers',
            [],
        );
    }

    public function test_preview_rejects_serial_numbers_that_are_not_16_digits(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,generate_date\nSERIAL-1,1234567890123456,1000,2026-11-03\n",
            'csv.import_errors.invalid_serial_no',
            ['line' => 2],
        );
    }

    public function test_preview_reports_duplicate_serials_within_the_csv(): void
    {
        config(['top_up_cards.chunk_size' => 1]);
        Storage::fake('local');
        $this->makeOffice('Office 31', 'Main Street', '31');
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "serial_no,pin,amount,generate_date\n2609111128316006,1234567890123456,1000,2026-11-03\n2609111128316006,2234567890123456,1000,2026-11-03\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        try {
            ImportGeneratedTopUpCardsJob::previewCsv($path);
            $this->fail('The CSV preview should reject repeated serial numbers in the file.');
        } catch (CsvImportException $exception) {
            $this->assertSame('csv.import_errors.duplicate_serial', $exception->translationKey);
            $this->assertSame(['line' => 3], $exception->replace);
        }
    }

    public function test_preview_rejects_pins_that_are_not_16_digits(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,generate_date\n2609111128316006,1234,1000,2026-11-03\n",
            'csv.import_errors.invalid_pin',
            ['line' => 2],
        );
    }

    public function test_preview_rejects_office_codes_not_in_the_office_table(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,generate_date\n2609111128996006,1234567890123456,1000,2026-11-03\n",
            'csv.import_errors.invalid_office_code',
            ['line' => 2, 'office_code' => '99'],
        );
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function assertPreviewError(string $contents, string $key, array $replace): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('cards.csv', $contents);
        $path = $file->store('imports/top-up-cards/validation', 'local');

        try {
            ImportGeneratedTopUpCardsJob::previewCsv($path);
            $this->fail('The CSV preview should have rejected this row.');
        } catch (CsvImportException $exception) {
            $this->assertSame($key, $exception->translationKey);
            $this->assertSame($replace, $exception->replace);
        }
    }
}
