<?php

namespace Tests\Unit;

use App\Jobs\ImportGeneratedTopUpCardsJob;
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

    public function test_preview_rejects_the_removed_status_column(): void
    {
        $this->assertPreviewError(
            "serial_no,pin,amount,expires_at,status\n2609111128316006,1234567890123456,1000,2030-12-31,active\n",
            'csv.import_errors.invalid_headers',
            [],
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
                    'amount_counts' => [['amount' => 1000, 'count' => 1]],
                    'total_cards' => 1,
                    'generate_date' => null,
                    'activated_at' => '2026-11-03',
                    'expires_at' => '2028-11-03',
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
        Office::query()->create(['name' => 'Office 31', 'address' => 'Main Street', 'cd' => '31']);
        $file = UploadedFile::fake()->createWithContent(
            'cards.csv',
            implode("\n", [
                'serial_no,pin,amount,expires_at',
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
        $this->assertSame('50.00', $card->amount);
        $this->assertSame('2028-11-03', $card->batch->expires_at->toDateString());
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
