<?php

namespace Tests\Unit;

use App\Http\Controllers\InOutManagement\CSV\TopUpCard as TopUpCardCsv;
use App\Jobs\ImportGeneratedTopUpCardsJob;
use App\Support\CsvImportException;
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
                    'office' => 'Office 31:31',
                    'count_50' => 0,
                    'count_100' => 0,
                    'count_250' => 0,
                    'count_500' => 0,
                    'total_points' => 1,
                    'expires_at' => '2030-12-31',
                ],
            ],
            $preview['rows'],
        );
        $this->assertSame(1, $preview['total_summaries']);
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
