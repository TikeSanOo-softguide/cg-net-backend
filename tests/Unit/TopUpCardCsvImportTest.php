<?php

namespace Tests\Unit;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\InOutManagement\CSV\TopUpCard as TopUpCardCsv;
use App\Jobs\ImportGeneratedTopUpCardsJob;
use App\Models\Batch;
use App\Models\Office;
use App\Models\TopUpCard;
use App\Support\CsvImportException;
use App\Support\TopUpCardPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TopUpCardCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_headers_are_reported_before_database_lookup(): void
    {
        $this->assertImportError(
            "wrong_serial_no,pin,amount,expires_at,status\nSERIAL-1,1234,1000,2030-12-31,pending\n",
            'csv.import_errors.invalid_headers',
            [],
        );
    }

    public function test_null_serial_is_reported_as_an_invalid_row(): void
    {
        $this->assertImportError(
            "serial_no,pin,amount,expires_at,status\n,1234,1000,2030-12-31,pending\n",
            'csv.import_errors.invalid_row',
            ['line' => 1],
        );
    }

    public function test_literal_null_serial_is_reported_as_an_invalid_row(): void
    {
        $this->assertImportError(
            "serial_no,pin,amount,expires_at,status\nnull,1234,1000,2030-12-31,pending\n",
            'csv.import_errors.invalid_row',
            ['line' => 1],
        );
    }

    public function test_preview_returns_validated_rows_without_pin_values(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->startOfDay());
        Storage::fake('local');
        \App\Models\Office::query()->create(['name' => 'Office 31', 'address' => 'Main Street', 'cd' => '31']);
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "serial_no,pin,amount,expires_at\n2609111128316006,1234567890123456,1000,2030-12-31\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame(
            [
                [
                    'office' => 'Office 31',
                    'count_50' => 0,
                    'count_100' => 0,
                    'count_250' => 0,
                    'count_500' => 0,
                    'total_points' => 1,
                    'generate_date' => null,
                    'activated_at' => '2026-11-03',
                    'expires_at' => '2028-11-03',
                ],
            ],
            $preview['rows'],
        );
        $this->assertSame(1, $preview['total_summaries']);
    }

    public function test_csv_import_uses_activation_date_for_card_expiry(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->startOfDay());
        Storage::fake('local');
        Office::query()->create(['name' => 'Office 88', 'address' => 'Main Street', 'cd' => '88']);
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
        $this->assertSame('2026-11-03', $card->activated_at->toDateString());
        $this->assertSame('2028-11-03', $card->expires_at->toDateString());
        $this->assertSame(50, $card->amount);
        $this->assertSame('2028-11-03', $card->batch->expires_at->toDateString());
    }

    public function test_pending_card_activation_resets_expiry_from_activation_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->startOfDay());
        $batch = Batch::factory()->create(['expires_at' => '2028-10-03']);
        $card = TopUpCard::factory()->create([
            'status' => TopUpCardStatus::Pending,
            'expires_at' => '2028-10-03',
            'batch_id' => $batch->id,
        ]);
        $csv = implode("\n", [
            'serial_no,pin,amount,expires_at,status',
            "{$card->serial_no},1234567890123456,{$card->amount},2028-10-03,pending",
        ]);

        TopUpCardCsv::import(UploadedFile::fake()->createWithContent('cards.csv', $csv));

        $card->refresh();
        $this->assertSame(TopUpCardStatus::Active, $card->status);
        $this->assertSame('2026-11-03', $card->activated_at->toDateString());
        $this->assertSame('2028-11-03', $card->expires_at->toDateString());
        $this->assertSame('2028-11-03', $batch->fresh()->expires_at->toDateString());
    }

    public function test_preview_accepts_generated_card_csv_without_expire_date_column(): void
    {
        Storage::fake('local');
        Office::query()->create(['name' => 'Office 88', 'address' => 'Main Street', 'cd' => '88']);
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
        Office::query()->create(['name' => 'Office 88', 'address' => 'Main Street', 'cd' => '88']);
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
        Office::query()->create(['name' => 'Office 88', 'address' => 'Main Street', 'cd' => '88']);
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "SerialNumber PIN_Code PointValue GenerateDate\n2610110123881001 1341-8663-1662-1366 50 2026-10-23\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        $preview = ImportGeneratedTopUpCardsJob::previewCsv($path);

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame('2026-10-23', $preview['rows'][0]['generate_date']);
        $this->assertSame(1, $preview['rows'][0]['count_50']);
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

    public function test_preview_reports_invalid_row_for_unsupported_status(): void
    {
        Storage::fake('local');
        \App\Models\Office::query()->create(['name' => 'Office 31', 'address' => 'Main Street', 'cd' => '31']);
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "serial_no,pin,amount,expires_at,status\n2609111128316006,1234567890123456,1000,2030-12-31,active\n",
        );
        $path = $file->store('imports/top-up-cards/validation', 'local');

        try {
            ImportGeneratedTopUpCardsJob::previewCsv($path);
            $this->fail('The unsupported status should be rejected.');
        } catch (CsvImportException $exception) {
            $this->assertSame('csv.import_errors.invalid_row', $exception->translationKey);
            $this->assertSame(['line' => 2], $exception->replace);
        }
    }

    public function test_preview_rejects_serial_numbers_that_are_not_16_digits(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,expires_at\nSERIAL-1,1234567890123456,1000,2030-12-31\n",
            'csv.import_errors.invalid_serial_no',
            ['line' => 2],
        );
    }

    public function test_preview_reports_duplicate_serials_within_the_csv(): void
    {
        Storage::fake('local');
        \App\Models\Office::query()->create(['name' => 'Office 31', 'address' => 'Main Street', 'cd' => '31']);
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            "serial_no,pin,amount,expires_at\n2609111128316006,1234567890123456,1000,2030-12-31\n2609111128316006,2234567890123456,1000,2030-12-31\n",
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
            "serial_no,pin,amount,expires_at\n2609111128316006,1234,1000,2030-12-31\n",
            'csv.import_errors.invalid_pin',
            ['line' => 2],
        );
    }

    public function test_preview_rejects_office_codes_not_in_the_office_table(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,expires_at\n2609111128996006,1234567890123456,1000,2030-12-31\n",
            'csv.import_errors.invalid_office_code',
            ['line' => 2, 'office_code' => '99'],
        );
    }

    /**
     * @param array<string, string|int> $replace
     */
    private function assertImportError(string $contents, string $key, array $replace): void
    {
        try {
            TopUpCardCsv::import(UploadedFile::fake()->createWithContent('cards.csv', $contents));
            $this->fail('The CSV import should have been rejected.');
        } catch (CsvImportException $exception) {
            $this->assertSame($key, $exception->translationKey);
            $this->assertSame($replace, $exception->replace);
        }
    }

    /**
     * @param array<string, string|int> $replace
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
