<?php

namespace Tests\Feature;

use App\Jobs\GenerateTopUpCardsJob;
use App\Http\Controllers\TopUpCard\TopUpCardController;
use App\Http\Requests\TopUpCard\GenerateTopUpCardsRequest;
use App\Models\Admin;
use App\Models\Office;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Support\AppPermissions;
use App\Support\GeneratesTopUpCards;
use App\Support\TopUpCardOffices;
use Illuminate\Bus\Batch as QueueBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TopUpCardChunkGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_large_generation_is_split_into_sequential_serial_chunks(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $office = Office::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => '88',
        ]);

        Queue::fake();

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 500, 'quantity' => 2500]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        $chunkSize = max(1, (int) config('top_up_cards.chunk_size', 500));

        Queue::assertPushed(GenerateTopUpCardsJob::class, (int) ceil(2500 / $chunkSize));

        $jobs = array_map(
            fn(array $payload): GenerateTopUpCardsJob => $payload['job'],
            Queue::pushedJobs()[GenerateTopUpCardsJob::class],
        );

        $this->assertSame(
            2500,
            array_sum(array_map(fn(GenerateTopUpCardsJob $job): int => $job->amounts[0]['quantity'], $jobs)),
        );

        foreach ($jobs as $job) {
            $job->handle();
        }

        $this->assertDatabaseCount('top_up_card', 0);
        $cards = collect(range(0, count($jobs) - 1))
            ->flatMap(fn(int $index): array => Cache::get(
                'top_up_card_generation:' . session('top_up_card_generation_token') . ':chunk:' . $index,
                [],
            )['cards'] ?? [])
            ->values();
        $this->assertCount(2500, $cards);
        $this->assertSame(
            range(1001, 3500),
            $cards
                ->pluck('serial_no')
                ->map(fn(string $serial): int => (int) substr($serial, 12))
                ->all(),
        );
    }

    public function test_empty_office_selection_generates_for_all_office_codes(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        Office::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => 11,
        ]);
        Office::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => 22,
        ]);
        Office::query()->create([
            'name' => 'Deleted Office',
            'address' => 'Gone Street',
            'cd' => 33,
        ])->delete();

        Queue::fake();

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 500, 'quantity' => 5]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        Queue::assertPushed(GenerateTopUpCardsJob::class, 2);

        $officeCodes = array_map(
            fn(array $payload): string => (string) $payload['job']->officeCode,
            Queue::pushedJobs()[GenerateTopUpCardsJob::class],
        );
        $this->assertSame(['11', '22'], $officeCodes);
    }

    public function test_generation_rejects_soft_deleted_offices(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $agent = Office::query()->create([
            'name' => 'Deleted Office',
            'address' => 'Gone Street',
            'cd' => 33,
            'phone' => '09123456791',
            'status' => 'active',
        ]);
        $agent->delete();

        $validator = Validator::make(
            [
                'amounts' => [['value' => 500, 'quantity' => 1]],
                'expires_at' => now()->addDays(30)->toDateString(),
                'office_ids' => [$agent->id],
            ],
            (new GenerateTopUpCardsRequest())->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('office_ids.0', $validator->errors()->toArray());
    }

    public function test_generation_rejects_when_quantity_times_agents_exceeds_max_cards(): void
    {
        config(['top_up_cards.max_cards' => 100]);

        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $first = Office::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => 11,
            'phone' => '09123456789',
            'status' => 'active',
        ]);
        $second = Office::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => 22,
            'phone' => '09123456790',
            'status' => 'active',
        ]);

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 500, 'quantity' => 60]],
                'expires_at' => now()->addDays(30)->toDateString(),
                'office_ids' => [$first->id, $second->id],
            ])
            ->assertSessionHasErrors('amounts');
    }

    public function test_generation_rejects_quantities_above_the_daily_counter_capacity(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        Office::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => '88',
        ]);

        Queue::fake();

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 50, 'quantity' => 9000]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertSessionHasErrors('amounts');

        Queue::assertNothingPushed();
    }

    public function test_office_specific_generation_is_grouped_by_office_code_and_amount(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        Office::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => '88',
        ]);

        Office::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => 99,
        ]);

        Queue::fake();

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [
                    ['value' => 50, 'quantity' => 10],
                    ['value' => 100, 'quantity' => 10],
                    ['value' => 250, 'quantity' => 10],
                    ['value' => 500, 'quantity' => 10],
                ],
                'expires_at' => now()->addDays(30)->toDateString(),
                'office_ids' => Office::query()->orderBy('id')->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        Queue::assertPushed(GenerateTopUpCardsJob::class, 8);

        $officeCodes = array_map(
            fn(array $payload): string => (string) $payload['job']->officeCode,
            Queue::pushedJobs()[GenerateTopUpCardsJob::class],
        );
        $this->assertSame(['88', '88', '88', '88', '99', '99', '99', '99'], $officeCodes);
    }

    public function test_serial_reservation_uses_sequence_table_not_existing_cards(): void
    {
        if (!Office::query()->where('cd', '88')->exists()) {
            Office::query()->create([
                'name' => 'Default Office',
                'address' => 'Main Street',
                'cd' => '88',
            ]);
        }

        TopUpCard::factory()->create([
            'serial_no' => GeneratesTopUpCards::serialNo('500', 2500),
        ]);

        $startingCounter = GeneratesTopUpCards::reserveSerialRange(1000, now());

        $this->assertSame(1001, $startingCounter);
        $this->assertSame(2001, GeneratesTopUpCards::reserveSerialRange(1, now()));
    }

    public function test_serial_uses_selected_agent_cd_and_cycles_for_multi_select(): void
    {
        $serialWithSingleAgent = GeneratesTopUpCards::serialNo('500', 1001, now(), 23);
        $this->assertStringContainsString('23', $serialWithSingleAgent);

        $serials = [
            GeneratesTopUpCards::serialNo('500', 1001, now(), 11),
            GeneratesTopUpCards::serialNo('500', 1002, now(), 24),
        ];

        $this->assertTrue(str_contains($serials[0], '11'));
        $this->assertTrue(str_contains($serials[1], '24'));
    }

    public function test_office_code_is_parsed_from_a_sixteen_digit_serial(): void
    {
        $this->assertSame('31', TopUpCardOffices::officeCodeFromSerialNo('2609111115311001'));
        $this->assertNull(TopUpCardOffices::officeCodeFromSerialNo('26091111153110000'));
    }

    public function test_serial_uses_the_sixteen_digit_layout_and_configured_batch_codes(): void
    {
        $productionDate = \Carbon\Carbon::create(2026, 9, 15);

        $serialNo = GeneratesTopUpCards::serialNo('50', 1001, $productionDate, '88');

        $this->assertSame('2609110115881001', $serialNo);
        $this->assertSame(16, strlen($serialNo));
        $this->assertSame('1101', GeneratesTopUpCards::batchCodeForAmount('50'));
        $this->assertSame('1201', GeneratesTopUpCards::batchCodeForAmount('100'));
        $this->assertSame('1501', GeneratesTopUpCards::batchCodeForAmount('250'));
        $this->assertSame('1111', GeneratesTopUpCards::batchCodeForAmount('500'));
        $this->assertSame('9005', GeneratesTopUpCards::batchCodeForAmount('5'));
        $this->assertSame('9010', GeneratesTopUpCards::batchCodeForAmount('10'));
        $this->assertSame('9020', GeneratesTopUpCards::batchCodeForAmount('20'));
        $this->assertSame('9030', GeneratesTopUpCards::batchCodeForAmount('30'));
        $this->assertSame('9035', GeneratesTopUpCards::batchCodeForAmount('35'));
    }

    public function test_daily_serial_counter_stops_at_9999(): void
    {
        $this->assertSame(1001, GeneratesTopUpCards::allocateSerialRange(8999, now()));

        $this->expectException(\RuntimeException::class);
        GeneratesTopUpCards::allocateSerialRange(1, now());
    }

    public function test_disjoint_serial_ranges_are_allocated_from_sequence_counter(): void
    {
        if (!Office::query()->where('cd', '88')->exists()) {
            Office::query()->create([
                'name' => 'Default Office',
                'address' => 'Main Street',
                'cd' => '88',
            ]);
        }

        $this->assertSame(1001, GeneratesTopUpCards::allocateSerialRange(1000, now()));
        TopUpCard::factory()->create([
            'serial_no' => GeneratesTopUpCards::serialNo('500', 1001),
        ]);

        $this->assertSame(2001, GeneratesTopUpCards::allocateSerialRange(1, now()));
    }

    public function test_serial_ranges_are_partitioned_by_batch_code_for_the_same_agent_and_day(): void
    {
        $this->assertSame(1001, GeneratesTopUpCards::allocateSerialRange(10, now(), '88', '1101'));
        $this->assertSame(1001, GeneratesTopUpCards::allocateSerialRange(10, now(), '88', '1201'));
        $this->assertSame(1011, GeneratesTopUpCards::allocateSerialRange(10, now(), '88', '1101'));
        $this->assertSame(1011, GeneratesTopUpCards::allocateSerialRange(10, now(), '88', '1201'));
    }

    public function test_generation_is_rejected_while_another_batch_is_processing(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $other = Admin::factory()->create();
        $other->assignRole(AppPermissions::SuperAdmin);

        Office::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => '88',
        ]);

        $token = 'processing-generation-token';
        Cache::put(
            'top_up_card_generation:' . $token,
            ['status' => 'processing', 'total_chunks' => 1, 'user_id' => $other->id],
            now()->addDay(),
        );
        Cache::put('top_up_card_generation:active', $token, now()->addDay());

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 500, 'quantity' => 10]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'top_up_cards.generation_in_progress');
    }

    public function test_job_retry_reuses_completed_export_without_persisting_cards(): void
    {
        if (!Office::query()->where('cd', '88')->exists()) {
            Office::query()->create([
                'name' => 'Default Office',
                'address' => 'Main Street',
                'cd' => '88',
            ]);
        }

        $actor = Admin::factory()->create();
        $token = (string) Str::uuid();
        $expiresAt = now()->addDays(30)->toDateString();
        $job = new GenerateTopUpCardsJob(
            [['value' => 500, 'quantity' => 5]],
            $expiresAt,
            (int) $actor->id,
            $token,
            now()->toIso8601String(),
            '88',
            0,
            1,
        );

        $job->handle();

        $this->assertDatabaseCount('top_up_card', 0);
        $this->assertDatabaseCount('batches', 0);

        $chunkKey = 'top_up_card_generation:' . $token . ':chunk:0';
        $completed = Cache::get($chunkKey);
        $this->assertIsArray($completed);
        $this->assertSame('completed', $completed['status']);
        $this->assertCount(5, $completed['cards']);

        Cache::put($chunkKey, [...$completed, 'status' => 'inserting'], now()->addDay());

        $job->handle();

        $this->assertDatabaseCount('top_up_card', 0);
        $this->assertDatabaseCount('batches', 0);

        $retry = Cache::get($chunkKey);
        $this->assertSame('completed', $retry['status']);
        $this->assertSame($completed['cards'], $retry['cards']);
    }

    public function test_generation_chunks_only_create_export_data(): void
    {
        if (!Office::query()->where('cd', '88')->exists()) {
            Office::query()->create([
                'name' => 'Default Office',
                'address' => 'Main Street',
                'cd' => '88',
            ]);
        }

        $actor = Admin::factory()->create();
        $expiresAt = now()->addDays(30)->toDateString();
        $token = (string) Str::uuid();
        $first = new GenerateTopUpCardsJob(
            [['value' => 500, 'quantity' => 4]],
            $expiresAt,
            (int) $actor->id,
            $token,
            now()->toIso8601String(),
            '88',
            0,
            2,
        );
        $second = new GenerateTopUpCardsJob(
            [['value' => 500, 'quantity' => 3]],
            $expiresAt,
            (int) $actor->id,
            $token,
            now()->toIso8601String(),
            '88',
            1,
            2,
        );

        $first->handle();
        $second->handle();

        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('top_up_card', 0);
        $this->assertCount(4, Cache::get('top_up_card_generation:' . $token . ':chunk:0')['cards']);
        $this->assertCount(3, Cache::get('top_up_card_generation:' . $token . ':chunk:1')['cards']);
    }

    public function test_activity_is_logged_with_succeeded_or_failed_status(): void
    {
        if (!Office::query()->where('cd', '88')->exists()) {
            Office::query()->create([
                'name' => 'Default Office',
                'address' => 'Main Street',
                'cd' => '88',
            ]);
        }

        $actor = Admin::factory()->create();
        $token = (string) Str::uuid();
        $expiresAt = now()->addDays(30)->toDateString();
        Cache::put(
            'top_up_card_generation:' . $token,
            [
                'status' => 'completed',
                'total_cards' => 5,
                'total_value' => 2500,
                'expires_at' => $expiresAt,
                'items' => [['office_cd' => '88', 'amount' => 500, 'quantity' => 5]],
                'user_id' => $actor->id,
            ],
            now()->addDay(),
        );

        $succeededBatch = Mockery::mock(QueueBatch::class);
        $succeededBatch->id = 'activity-batch-succeeded';
        $succeededBatch->totalJobs = 1;
        $succeededBatch->failedJobs = 0;
        $succeededBatch->shouldReceive('processedJobs')->andReturn(1);

        TopUpCardController::logGenerationFinished($token, $succeededBatch, 'succeeded');

        $this->assertDatabaseHas('activity_log', [
            'description' => 'top_up_cards_generation_succeeded',
            'event' => 'succeeded',
            'causer_id' => $actor->id,
            'subject_id' => null,
        ]);

        $activity = \Spatie\Activitylog\Models\Activity::query()
            ->where('description', 'top_up_cards_generation_succeeded')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(2500, $activity->properties['total_value']);
        $this->assertSame(5, $activity->properties['quantity']);
        $this->assertSame($token, $activity->properties['generation_token']);
        $this->assertEquals(
            [['office_cd' => '88', 'amount' => 500, 'quantity' => 5]],
            collect($activity->properties['items'])->all(),
        );

        $failedToken = (string) Str::uuid();
        Cache::put(
            'top_up_card_generation:' . $failedToken,
            [
                'status' => 'failed',
                'total_cards' => 5,
                'total_value' => 2500,
                'expires_at' => $expiresAt,
                'items' => [['office_cd' => '88', 'amount' => 500, 'quantity' => 5]],
                'user_id' => $actor->id,
            ],
            now()->addDay(),
        );

        $failedBatch = Mockery::mock(QueueBatch::class);
        $failedBatch->id = 'activity-batch-failed';
        $failedBatch->totalJobs = 1;
        $failedBatch->failedJobs = 1;
        $failedBatch->shouldReceive('processedJobs')->andReturn(0);

        TopUpCardController::logGenerationFinished($failedToken, $failedBatch, 'failed');

        $this->assertDatabaseHas('activity_log', [
            'description' => 'top_up_cards_generation_failed',
            'event' => 'failed',
            'causer_id' => $actor->id,
        ]);
    }

    public function test_previous_generation_cache_is_cleared_before_new_batch_starts(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        Office::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => '88',
        ]);

        $oldToken = 'old-generation-token';
        Cache::put(
            'top_up_card_generation:' . $oldToken,
            ['status' => 'completed', 'total_chunks' => 2, 'cards' => [['serial_no' => 'old']]],
            now()->addDay(),
        );
        Cache::put(
            'top_up_card_generation:' . $oldToken . ':chunk:0',
            ['status' => 'completed', 'cards' => [['serial_no' => 'old']]],
            now()->addDay(),
        );
        Cache::put(
            'top_up_card_generation:' . $oldToken . ':chunk:1',
            ['status' => 'completed', 'cards' => [['serial_no' => 'old-2']]],
            now()->addDay(),
        );

        $this->withSession(['top_up_card_generation_token' => $oldToken])
            ->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 500, 'quantity' => 10]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        $this->assertNotSame($oldToken, session('top_up_card_generation_token'));
        $this->assertNull(Cache::get('top_up_card_generation:' . $oldToken));
        $this->assertNull(Cache::get('top_up_card_generation:' . $oldToken . ':chunk:0'));
    }
}
