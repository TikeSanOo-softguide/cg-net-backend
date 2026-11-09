<?php

namespace Tests\Feature;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\TopUpCard\TopUpCardController;
use App\Jobs\GenerateTopUpCardsJob;
use App\Jobs\ImportGeneratedTopUpCardsJob;
use App\Models\Admin;
use App\Models\Office;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Models\User;
use App\Support\AppPermissions;
use App\Support\CsvImportException;
use App\Support\TopUpCardPin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Bus\Batch as QueueBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TopUpCardManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Cache::flush();
        RolePermissionSeeder::sync();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_cannot_view_top_up_cards(): void
    {
        $this->get('/top-up-cards/batch')->assertRedirect('/login');
        $this->get('/top-up-cards/redeem-history')->assertRedirect('/login');
    }

    public function test_admins_without_permission_are_forbidden(): void
    {
        $this->autoGrantPermissions = false;
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')->get('/top-up-cards/batch')->assertForbidden();

        $this->actingAs($admin, 'web')->get('/top-up-cards/redeem-history')->assertForbidden();

        $this->actingAs($admin, 'web')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 1000, 'quantity' => 1]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_admins_can_generate_one_row_per_quantity(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $this->makeOffice('Default Office', 'Main Street', '88');

        Queue::fake();

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/batch')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/Generate')
                    ->where('amounts', [50, 100, 150, 500]),
            );

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 3000, 'quantity' => 5], ['value' => 10000, 'quantity' => 2]],
                'expires_at' => now()->addDays(90)->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        Queue::assertPushed(GenerateTopUpCardsJob::class, 2);

        foreach (Queue::pushedJobs()[GenerateTopUpCardsJob::class] as $payload) {
            $payload['job']->handle();
        }

        $this->assertDatabaseCount('top_up_card', 0);
        $this->assertDatabaseCount('batches', 0);

        // Activity is recorded by the batch finally() callback after jobs finish,
        // not when the generate request is accepted.
        $this->assertDatabaseMissing('activity_log', [
            'description' => 'top_up_cards_generation_succeeded',
        ]);

        $token = session('top_up_card_generation_token');
        $this->assertIsString($token);

        $batch = Mockery::mock(QueueBatch::class);
        $batch->id = 'test-batch-id';
        $batch->totalJobs = 2;
        $batch->failedJobs = 0;
        $batch->shouldReceive('processedJobs')->andReturn(2);

        TopUpCardController::logGenerationFinished($token, $batch, 'succeeded');

        $this->assertDatabaseHas('activity_log', [
            'description' => 'top_up_cards_generation_succeeded',
            'event' => 'succeeded',
            'causer_id' => $actor->id,
        ]);

        $csvResponse = $this->actingAs($actor, 'web')->get('/top-up-cards/export')->assertOk();
        $csv = $csvResponse->streamedContent();

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent('generated.csv', $csv),
            ])
            ->assertRedirect('/top-up-cards/card-import');

        Queue::assertPushed(ImportGeneratedTopUpCardsJob::class, 1);
        Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job']->handle();

        $cards = TopUpCard::query()->get();
        $this->assertCount(7, $cards);
        $this->assertSame(5, TopUpCard::query()->where('amount', 3000)->count());
        $this->assertSame(2, TopUpCard::query()->where('amount', 10000)->count());
        $this->assertTrue(
            $cards->every(fn(TopUpCard $card): bool => $card->generate_date !== null && $card->activated_at !== null),
        );
        $this->assertSame(1, Batch::query()->count());
    }

    public function test_admins_can_import_csv_with_a_single_data_row(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        Storage::fake('local');
        Queue::fake();
        $office = $this->makeOffice('Office 31', 'Main Street', '31');
        $serialNo = '2609111128316006';

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent(
                    'cards.csv',
                    "serial_no,pin,amount,expires_at\n{$serialNo},1234567890123456,1000,2030-12-31\n",
                ),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/top-up-cards/card-import');

        Queue::assertPushed(ImportGeneratedTopUpCardsJob::class, 1);
        Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job']->handle();

        $card = TopUpCard::query()->where('serial_no', $serialNo)->firstOrFail();
        $this->assertSame(TopUpCardStatus::Active, $card->status);
        $this->assertSame($office->id, $card->office_id);
        $this->assertDatabaseHas('batches', [
            'id' => $card->batch_id,
            'quantity' => 1,
            'total_value' => 1000,
        ]);
    }

    public function test_admins_can_import_csv_cards_while_preserving_generation_flow(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        Storage::fake('local');
        Queue::fake();
        $office31 = $this->makeOffice('Office 31', 'Main Street', '31');
        $office11 = $this->makeOffice('Office 11', 'Second Street', '11');

        $csv = implode("\n", [
            'serial_no , pin , amount, expires_at',
            '2609111128316006,1234567890123456,1000,2030-12-31',
            '2609110128112982,2234567890123456,2500,2031-01-15',
        ]);

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
                'return' => 'assign',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/top-up-cards/card-import');

        Queue::assertPushed(ImportGeneratedTopUpCardsJob::class, 1);
        Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job']->handle();

        $batchId = TopUpCard::query()->where('serial_no', '2609111128316006')->value('batch_id');
        $this->assertNotNull($batchId);

        $this->assertDatabaseHas('top_up_card', [
            'serial_no' => '2609111128316006',
            'amount' => 1000,
            'status' => TopUpCardStatus::Active,
            'redeemed_at' => null,
            'redeemed_by' => null,
            'office_id' => $office31->id,
            'batch_id' => $batchId,
            'ledger_transaction_id' => null,
        ]);

        $this->assertDatabaseHas('top_up_card', [
            'serial_no' => '2609110128112982',
            'amount' => 2500,
            'status' => TopUpCardStatus::Active,
            'redeemed_at' => null,
            'redeemed_by' => null,
            'office_id' => $office11->id,
            'batch_id' => $batchId,
            'ledger_transaction_id' => null,
        ]);

        $this->assertDatabaseHas('batches', [
            'id' => $batchId,
            'quantity' => 2,
            'total_value' => 3500,
        ]);

        $batchMetadata = Batch::query()->findOrFail($batchId)->metadata;
        $this->assertSame(2, $batchMetadata['total_cards']);
        $this->assertSame('3500', $batchMetadata['total_value']);
        $this->assertSame(
            [
                ['office_cd' => '31', 'amount' => 1000, 'quantity' => 1],
                ['office_cd' => '11', 'amount' => 2500, 'quantity' => 1],
            ],
            $batchMetadata['items'],
        );
        $this->assertArrayNotHasKey('source', $batchMetadata);
        $this->assertArrayNotHasKey('generation_token', $batchMetadata);
        $this->assertArrayNotHasKey('amounts', $batchMetadata);
    }

    public function test_csv_import_reports_duplicate_pin_and_does_not_create_a_batch(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        Storage::fake('local');
        Queue::fake();
        $this->makeOffice('Office 31', 'Main Street', '31');
        $duplicatePin = '1234567890123456';
        TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($duplicatePin)]);
        $initialBatchCount = Batch::query()->count();
        $csv = "serial_no,pin,amount,expires_at\n2609111128316007,{$duplicatePin},1000,2030-12-31\n";

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
                'return' => 'assign',
            ])
            ->assertRedirect('/top-up-cards/card-import');

        $job = Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job'];

        try {
            $job->handle();
            $this->fail('An import must reject a PIN that already exists.');
        } catch (CsvImportException $exception) {
            $this->assertSame('csv.import_errors.duplicate_pin', $exception->translationKey);
            $this->assertSame(['line' => 2], $exception->replace);
            $job->failed($exception);
        }

        $this->assertSame($initialBatchCount, Batch::query()->count());
        $this->assertSame(
            'csv.import_errors.duplicate_pin',
            Cache::get('top_up_card_generation:' . $job->token)['message'],
        );
        $this->assertSame(['line' => 2], Cache::get('top_up_card_generation:' . $job->token)['message_replace']);
    }

    public function test_csv_import_rejects_blank_values_in_any_column_before_creating_a_batch(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        Storage::fake('local');
        Queue::fake();
        $csv = "serial_no,pin,amount,expires_at\nTOPUP-BLANK-COLUMN-0001,1234567890123456,1000,\n";

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
                'return' => 'assign',
            ])
            ->assertRedirect('/top-up-cards/card-import');

        $job = Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job'];

        try {
            $job->handle();
            $this->fail('An import must reject rows with blank values in any column.');
        } catch (CsvImportException $exception) {
            $this->assertSame('csv.import_errors.invalid_row', $exception->translationKey);
            $this->assertSame(['line' => 2], $exception->replace);
            $job->failed($exception);
        }

        $this->assertSame(0, Batch::query()->count());
    }

    public function test_csv_import_validates_all_rows_before_checking_database_duplicates(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        Storage::fake('local');
        Queue::fake();
        $this->makeOffice('Office 31', 'Main Street', '31');
        TopUpCard::factory()->create(['serial_no' => '2609111128316006']);
        $initialBatchCount = Batch::query()->count();
        $csv = implode("\n", [
            'serial_no,pin,amount,expires_at',
            '2609111128316006,5234567890123456,1000,2030-12-31',
            '2609111128316007,6234567890123456,invalid,2030-12-31',
        ]);

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
                'return' => 'assign',
            ])
            ->assertRedirect('/top-up-cards/card-import');

        $job = Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job'];

        try {
            $job->handle();
            $this->fail('The invalid amount must be reported before database duplicates are checked.');
        } catch (CsvImportException $exception) {
            $this->assertSame('csv.import_errors.invalid_row', $exception->translationKey);
            $this->assertSame(['line' => 3], $exception->replace);
        }

        $this->assertSame($initialBatchCount, Batch::query()->count());
    }

    public function test_csv_import_rolls_back_batch_when_card_insert_fails(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        Storage::fake('local');
        Queue::fake();
        $this->makeOffice('Office 31', 'Main Street', '31');
        $csv = "serial_no,pin,amount,expires_at\n2609111128316008,3234567890123456,1000,2030-12-31\n";

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
                'return' => 'assign',
            ])
            ->assertRedirect('/top-up-cards/card-import');

        $job = Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job'];
        DB::unprepared(
            "CREATE TRIGGER fail_top_up_card_insert BEFORE INSERT ON top_up_card BEGIN SELECT RAISE(ABORT, 'simulated insert failure'); END",
        );

        try {
            $job->handle();
            $this->fail('The simulated card insert failure should abort the import.');
        } catch (\Throwable) {
        }

        $this->assertSame(0, Batch::query()->count());
        $this->assertSame(0, TopUpCard::query()->count());
    }

    public function test_deleting_a_batch_is_blocked_while_top_up_cards_exist(): void
    {
        $batch = Batch::factory()->create();
        $card = TopUpCard::factory()->create(['batch_id' => $batch->id]);

        try {
            $batch->delete();
            $this->fail('Deleting a batch with top-up cards must fail due to FK restrict.');
        } catch (\Throwable) {
            // sqlite FK / restrictOnDelete
        }

        $this->assertDatabaseHas('batches', ['id' => $batch->id]);
        $this->assertDatabaseHas('top_up_card', ['id' => $card->id]);
    }

    public function test_index_filters_by_serial_status_and_amount(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $match = TopUpCard::factory()->create([
            'serial_no' => 'TOPUP-AAAA-BBBB-CCCC',
            'amount' => 5000,
            'status' => TopUpCardStatus::Active,
        ]);
        TopUpCard::factory()->create([
            'serial_no' => 'TOPUP-DDDD-EEEE-FFFF',
            'amount' => 1000,
            'status' => TopUpCardStatus::Blocked,
        ]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/batch?search=AAAA&status=valid&amount=5000')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/Generate')
                    ->has('cards.data', 1)
                    ->where('cards.data.0.id', $match->id),
            );
    }

    public function test_newly_created_office_is_immediately_available_for_generation(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $existingOffice = $this->makeOffice('Existing Office', 'Main Street', '11');

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/offices', [
                'name_en' => 'New Office',
                'name_mm' => 'ရုံးအသစ်',
                'name_zh' => '新办公室',
                'cd' => '22',
                'address_en' => 'Second Street',
                'address_mm' => 'လိပ်စာအသစ်',
                'address_zh' => '第二街',
                'phone_num1' => '09123456780',
                'telegram_link' => 'https://t.me/new-office',
                'wechat_link' => 'https://wechat.example/new-office',
                'viber_link' => 'viber://chat?number=09123456780',
            ])
            ->assertSessionHasNoErrors();

        $newOffice = Office::query()->where('cd', '22')->firstOrFail();
        $this->assertSame('https://t.me/new-office', $newOffice->telegram_link);
        $this->assertSame('https://wechat.example/new-office', $newOffice->wechat_link);
        $this->assertSame('viber://chat?number=09123456780', $newOffice->viber_link);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/batch')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/Generate')
                    ->where('offices', [
                        ['id' => $existingOffice->id, 'name' => 'Existing Office', 'code' => '11'],
                        ['id' => $newOffice->id, 'name' => 'New Office', 'code' => '22'],
                    ]),
            );
    }

    public function test_office_crud_uses_localized_fields_and_contact_details(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $office = $this->makeOffice('Old office', 'Old street', '41');

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/offices')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/Office')
                    ->where('offices.data.0.name_en', 'Old office')
                    ->where('offices.data.0.address_en', 'Old street')
                    ->where('offices.data.0.phone_num1', '09123456789')
                    ->where('offices.data.0.telegram_link', null)
                    ->where('offices.data.0.wechat_link', null)
                    ->where('offices.data.0.viber_link', null),
            );

        $this->actingAs($actor, 'web')
            ->put("/top-up-cards/offices/{$office->id}", [
                'name_en' => 'Updated Office',
                'name_mm' => 'ပြင်ဆင်ထားသော ရုံး',
                'name_zh' => '更新后的办公室',
                'cd' => '42',
                'address_en' => 'Updated Street',
                'address_mm' => 'ပြင်ဆင်ထားသော လိပ်စာ',
                'address_zh' => '更新后的地址',
                'phone_num1' => '09123456780',
                'phone_num2' => '09123456781',
                'phone_num3' => null,
                'telegram_link' => 'https://t.me/updated-office',
                'wechat_link' => 'https://wechat.example/updated-office',
                'viber_link' => 'viber://chat?number=09123456780',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'name_en' => 'Updated Office',
            'name_mm' => 'ပြင်ဆင်ထားသော ရုံး',
            'name_zh' => '更新后的办公室',
            'address_en' => 'Updated Street',
            'address_mm' => 'ပြင်ဆင်ထားသော လိပ်စာ',
            'address_zh' => '更新后的地址',
            'cd' => '42',
            'phone_num1' => '09123456780',
            'phone_num2' => '09123456781',
            'phone_num3' => null,
            'telegram_link' => 'https://t.me/updated-office',
            'wechat_link' => 'https://wechat.example/updated-office',
            'viber_link' => 'viber://chat?number=09123456780',
        ]);
    }

    public function test_admins_can_void_a_valid_card(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $card = TopUpCard::factory()->create();

        $this->actingAs($actor, 'web')
            ->patch('/top-up-cards/' . $card->id . '/void')
            ->assertRedirect();

        $this->assertSame(TopUpCardStatus::Blocked, $card->fresh()->status);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'top_up_card_voided',
            'subject_id' => $card->id,
            'causer_id' => $actor->id,
        ]);
    }

    public function test_export_requires_a_generated_batch(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        Queue::fake();

        $this->makeOffice('Default Office', 'Main Street', '88');

        $this->actingAs($actor, 'web')->get('/top-up-cards/export')->assertNotFound();

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 50, 'quantity' => 1]],
                'expires_at' => '2028-10-23',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        Queue::assertPushed(GenerateTopUpCardsJob::class, 1);
        Queue::pushedJobs()[GenerateTopUpCardsJob::class][0]['job']->handle();

        $response = $this->actingAs($actor, 'web')
            ->get('/top-up-cards/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csvLines = array_values(array_filter(preg_split('/\r\n|\n|\r/', trim($response->streamedContent())) ?: []));
        $this->assertSame(
            ['SerialNumber', 'PIN_Code', 'PointValue', 'GenerateDate'],
            str_getcsv($csvLines[0]),
        );
        $csvRow = str_getcsv($csvLines[1]);
        $this->assertCount(4, $csvRow);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $csvRow[1]);
        $this->assertSame('50', $csvRow[2]);
        $this->assertSame(now()->toDateString(), $csvRow[3]);
        $this->assertStringNotContainsString('*', $csvLines[0]);
    }

    public function test_office_assignment_lists_batches_with_cards_regardless_of_status(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $blocked = TopUpCard::factory()->create(['status' => TopUpCardStatus::Blocked]);
        $active = TopUpCard::factory()->create(['status' => TopUpCardStatus::Active]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/card-import')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/CardImport')
                    ->has('batchPage.data', 2)
                    ->where('batchPage.data.0.id', $active->batch_id)
                    ->where('batchPage.data.1.id', $blocked->batch_id)
                    ->where('filters.status', ''),
            );
    }

    public function test_office_assignment_filters_by_batch_status(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $activeBatch = Batch::factory()->create(['status' => 'active']);
        $blockedBatch = Batch::factory()->create(['status' => 'blocked']);
        TopUpCard::factory()->create(['batch_id' => $activeBatch->id, 'status' => TopUpCardStatus::Active]);
        TopUpCard::factory()->create(['batch_id' => $blockedBatch->id, 'status' => TopUpCardStatus::Active]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/card-import?status=blocked')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/CardImport')
                    ->has('batchPage.data', 1)
                    ->where('batchPage.data.0.id', $blockedBatch->id)
                    ->where('batchPage.data.0.status', 'blocked'),
            );
    }

    public function test_active_batch_can_be_voided(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $batch = Batch::factory()->create(['status' => 'active']);

        $this->actingAs($actor, 'web')
            ->patch('/top-up-cards/batches/' . $batch->id . '/void')
            ->assertRedirect();

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => 'blocked',
        ]);
    }

    public function test_office_assign_csv_import_rejects_existing_active_cards(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        Storage::fake('local');
        Queue::fake();
        $batch = Batch::query()->create([
            'batch_no' => 'IMPORT-BATCH-0001',
            'total_value' => 1000,
            'quantity' => 1,
            'status' => 'active',
            'expires_at' => now()->addDays(30)->toDateString(),
            'metadata' => [
                'items' => [['office_cd' => '88', 'amount' => 1000, 'quantity' => 1]],
            ],
        ]);
        $this->makeOffice('Office 88', 'Main Street', '88');
        $card = TopUpCard::factory()->create([
            'serial_no' => '2609111128886006',
            'status' => TopUpCardStatus::Active,
            'batch_id' => $batch->id,
        ]);

        $csv = "serial_no,pin,amount,expires_at\n2609111128886006,1234567890123456,1000,{$card->expires_at->toDateString()}\n";

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/cards/import', [
                'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
                'return' => 'assign',
            ])
            ->assertRedirect('/top-up-cards/card-import');

        Queue::assertPushed(ImportGeneratedTopUpCardsJob::class, 1);
        $job = Queue::pushedJobs()[ImportGeneratedTopUpCardsJob::class][0]['job'];

        try {
            $job->handle();
            $this->fail('An import must reject a serial number that already exists.');
        } catch (CsvImportException $exception) {
            $this->assertSame('csv.import_errors.serial_already_exists', $exception->translationKey);
            $this->assertSame(['line' => 2], $exception->replace);
            $job->failed($exception);
        }

        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
    }

    public function test_admins_can_view_redeem_history(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $customer = User::factory()->create(['name' => 'Aung Aung', 'phone' => '09111111111']);
        $redeemed = TopUpCard::factory()
            ->redeemed($customer)
            ->create([
                'serial_no' => 'TOPUP-REDE-EMED-0001',
                'amount' => 3000,
            ]);
        TopUpCard::factory()->create([
            'serial_no' => 'TOPUP-VALID-CARD-0001',
            'amount' => 5000,
        ]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/redeem-history')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/History')
                    ->has('cards.data', 1)
                    ->where('cards.data.0.id', $redeemed->id)
                    ->where('stats.total', 1)
                    ->where('recent.0.id', $redeemed->id),
            );

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/redeem-history?search=Aung')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('TopUpCards/History')->has('cards.data', 1));
    }

    public function test_card_history_can_filter_by_office_and_returns_office_name_and_code(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $office = $this->makeOffice('Central Office', 'Main Street', '31');
        $otherOffice = $this->makeOffice('North Office', 'North Street', '11');
        $matchingCard = TopUpCard::factory()->create(['office_id' => $office->id]);
        TopUpCard::factory()->create(['office_id' => $otherOffice->id]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/card-history?office=' . $office->id)
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/CardHistory')
                    ->has('cards.data', 1)
                    ->where('cards.data.0.id', $matchingCard->id)
                    ->where('cards.data.0.office.name', 'Central Office')
                    ->where('cards.data.0.office.code', '31')
                    ->where('cards.per_page', 100)
                    ->where('offices.0.code', '31')
                    ->where('filters.office', (string) $office->id),
            );
    }

    public function test_card_history_stats_do_not_include_a_pending_status(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        TopUpCard::factory()->create(['status' => TopUpCardStatus::Active]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/card-history?status=pending')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('TopUpCards/CardHistory')
                    ->where('filters.status', '')
                    ->where('stats', [
                        'total' => 1,
                        'active' => 1,
                        'used' => 0,
                        'expired' => 0,
                        'blocked' => 0,
                    ]),
            );
    }
}
