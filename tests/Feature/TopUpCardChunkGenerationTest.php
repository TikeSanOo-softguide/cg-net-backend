<?php

namespace Tests\Feature;

use App\Jobs\GenerateTopUpCardsJob;
use App\Http\Controllers\TopUpCard\TopUpCardController;
use App\Http\Requests\TopUpCard\GenerateTopUpCardsRequest;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Support\AppPermissions;
use App\Support\GeneratesTopUpCards;
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

        Agent::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => 88,
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

        $this->assertDatabaseCount('top_up_card', 2500);
        $this->assertSame(
            range(1001, 3500),
            TopUpCard::query()
                ->orderBy('id')
                ->pluck('serial_no')
                ->map(fn(string $serial): int => (int) substr($serial, -6))
                ->all(),
        );
    }

    public function test_empty_agent_selection_generates_for_all_agent_codes(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        Agent::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => 11,
        ]);
        Agent::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => 22,
        ]);
        Agent::query()->create([
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

        $agentCodes = array_map(
            fn(array $payload): string => (string) $payload['job']->agentCode,
            Queue::pushedJobs()[GenerateTopUpCardsJob::class],
        );
        $this->assertSame(['11', '22'], $agentCodes);
    }

    public function test_generation_rejects_soft_deleted_agents(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $agent = Agent::query()->create([
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
                'agent_ids' => [$agent->id],
            ],
            (new GenerateTopUpCardsRequest())->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('agent_ids.0', $validator->errors()->toArray());
    }

    public function test_generation_rejects_when_quantity_times_agents_exceeds_max_cards(): void
    {
        config(['top_up_cards.max_cards' => 100]);

        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $first = Agent::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => 11,
            'phone' => '09123456789',
            'status' => 'active',
        ]);
        $second = Agent::query()->create([
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
                'agent_ids' => [$first->id, $second->id],
            ])
            ->assertSessionHasErrors('amounts');
    }

    public function test_agent_specific_generation_is_grouped_by_agent_code_and_amount(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        Agent::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => 88,
        ]);

        Agent::query()->create([
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
                'agent_ids' => Agent::query()->orderBy('id')->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        Queue::assertPushed(GenerateTopUpCardsJob::class, 8);

        $agentCodes = array_map(
            fn(array $payload): string => (string) $payload['job']->agentCode,
            Queue::pushedJobs()[GenerateTopUpCardsJob::class],
        );
        $this->assertSame(['88', '88', '88', '88', '99', '99', '99', '99'], $agentCodes);
    }

    public function test_serial_reservation_uses_sequence_table_not_existing_cards(): void
    {
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

    public function test_disjoint_serial_ranges_are_allocated_from_sequence_counter(): void
    {
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

        Agent::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => 88,
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

    public function test_job_retry_after_successful_insert_is_idempotent(): void
    {
        $actor = Admin::factory()->create();
        $token = (string) Str::uuid();
        $expiresAt = now()->addDays(30)->toDateString();
        $cardBatch = GeneratesTopUpCards::createGenerationBatch($expiresAt, 5, 2500, [
            'items' => [
                ['agent_cd' => '88', 'amount' => 500, 'quantity' => 5],
            ],
        ], $token);

        $job = new GenerateTopUpCardsJob(
            [['value' => 500, 'quantity' => 5]],
            $expiresAt,
            (int) $actor->id,
            $token,
            now()->toIso8601String(),
            '88',
            0,
            1,
            (int) $cardBatch->id,
        );

        $job->handle();

        $this->assertDatabaseCount('top_up_card', 5);
        $this->assertSame(1, Batch::query()->count());
        $this->assertTrue(
            TopUpCard::query()->where('batch_id', $cardBatch->id)->count() === 5,
        );

        $chunkKey = 'top_up_card_generation:' . $token . ':chunk:0';
        $completed = Cache::get($chunkKey);
        $this->assertIsArray($completed);
        $this->assertSame('completed', $completed['status']);
        $this->assertCount(5, $completed['cards']);

        $serials = TopUpCard::query()->orderBy('id')->pluck('serial_no')->all();

        // Crash after insert + PIN cache write, before completed ack.
        Cache::put($chunkKey, [...$completed, 'status' => 'inserting'], now()->addDay());

        $job->handle();

        $this->assertDatabaseCount('top_up_card', 5);
        $this->assertSame($serials, TopUpCard::query()->orderBy('id')->pluck('serial_no')->all());

        $retry = Cache::get($chunkKey);
        $this->assertSame('completed', $retry['status']);
        $this->assertSame($completed['cards'], $retry['cards']);
    }

    public function test_generation_uses_one_batch_for_all_chunks(): void
    {
        $actor = Admin::factory()->create();
        $expiresAt = now()->addDays(30)->toDateString();
        $token = (string) Str::uuid();
        $cardBatch = GeneratesTopUpCards::createGenerationBatch($expiresAt, 7, 3500, [
            'items' => [
                ['agent_cd' => '88', 'amount' => 500, 'quantity' => 7],
            ],
        ], $token);

        $first = new GenerateTopUpCardsJob(
            [['value' => 500, 'quantity' => 4]],
            $expiresAt,
            (int) $actor->id,
            $token,
            now()->toIso8601String(),
            '88',
            0,
            2,
            (int) $cardBatch->id,
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
            (int) $cardBatch->id,
        );

        $first->handle();
        $second->handle();

        $this->assertDatabaseCount('batches', 1);
        $this->assertDatabaseCount('top_up_card', 7);
        $this->assertSame(7, TopUpCard::query()->where('batch_id', $cardBatch->id)->count());
        $this->assertSame($cardBatch->batch_no, Batch::query()->value('batch_no'));
    }

    public function test_activity_is_logged_with_succeeded_or_failed_status(): void
    {
        $actor = Admin::factory()->create();
        $token = (string) Str::uuid();
        $expiresAt = now()->addDays(30)->toDateString();
        $cardBatch = GeneratesTopUpCards::createGenerationBatch(
            $expiresAt,
            5,
            2500,
            [
                'items' => [
                    ['agent_cd' => '88', 'amount' => 500, 'quantity' => 5],
                ],
            ],
            $token,
        );

        Cache::put(
            'top_up_card_generation:' . $token,
            [
                'status' => 'completed',
                'total_cards' => 5,
                'total_value' => 2500,
                'expires_at' => $expiresAt,
                'top_up_batch_id' => $cardBatch->id,
                'top_up_batch_no' => $cardBatch->batch_no,
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
            'subject_type' => $cardBatch->getMorphClass(),
            'subject_id' => $cardBatch->id,
        ]);

        $activity = \Spatie\Activitylog\Models\Activity::query()
            ->where('description', 'top_up_cards_generation_succeeded')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($cardBatch->batch_no, $activity->properties['batch_no']);
        $this->assertSame(2500, $activity->properties['total_value']);
        $this->assertSame(5, $activity->properties['quantity']);
        $this->assertSame($cardBatch->id, $activity->properties['batch_id']);
        $this->assertEquals(
            [['agent_cd' => '88', 'amount' => 500, 'quantity' => 5]],
            collect($activity->properties['items'])->all(),
        );

        $failedToken = (string) Str::uuid();
        $failedBatchModel = GeneratesTopUpCards::createGenerationBatch(
            $expiresAt,
            5,
            2500,
            [
                'items' => [
                    ['agent_cd' => '88', 'amount' => 500, 'quantity' => 5],
                ],
            ],
            $failedToken,
        );

        Cache::put(
            'top_up_card_generation:' . $failedToken,
            [
                'status' => 'failed',
                'total_cards' => 5,
                'total_value' => 2500,
                'expires_at' => $expiresAt,
                'top_up_batch_id' => $failedBatchModel->id,
                'top_up_batch_no' => $failedBatchModel->batch_no,
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

        Agent::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => 88,
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
