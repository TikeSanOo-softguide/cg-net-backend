<?php

namespace Tests\Feature;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\TopUpCard\TopUpCardController;
use App\Jobs\GenerateTopUpCardsJob;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Models\User;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Bus\Batch as QueueBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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

        Agent::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => 88,
        ]);

        Queue::fake();

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/batch')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('TopUpCards/Generate'));

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

        $cards = TopUpCard::query()->get();

        $this->assertCount(7, $cards);
        $this->assertSame(5, TopUpCard::query()->where('amount', 3000)->count());
        $this->assertSame(2, TopUpCard::query()->where('amount', 10000)->count());
        $this->assertCount(7, $cards->pluck('serial_no')->unique());
        $this->assertTrue($cards->every(fn(TopUpCard $card) => ctype_digit($card->serial_no)));
        $this->assertTrue($cards->every(fn(TopUpCard $card) => $card->status === TopUpCardStatus::Pending));
        $this->assertTrue($cards->every(function (TopUpCard $card): bool {
            return strlen((string) $card->getAttributes()['pin']) === 64
                && ctype_xdigit((string) $card->getAttributes()['pin']);
        }));

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
    }

    public function test_index_filters_by_serial_status_and_amount(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $match = TopUpCard::factory()->create([
            'serial_no' => 'TOPUP-AAAA-BBBB-CCCC',
            'amount' => 5000,
            'status' => TopUpCardStatus::Pending,
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

        Agent::query()->create([
            'name' => 'Default Office',
            'address' => 'Main Street',
            'cd' => 88,
        ]);

        $this->actingAs($actor, 'web')->get('/top-up-cards/export')->assertNotFound();

        $this->actingAs($actor, 'web')
            ->from('/top-up-cards/batch')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 1000, 'quantity' => 1]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect('/top-up-cards/batch');

        Queue::assertPushed(GenerateTopUpCardsJob::class, 1);
        Queue::pushedJobs()[GenerateTopUpCardsJob::class][0]['job']->handle();

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_agent_card_table_excludes_pending_cards(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $pending = TopUpCard::factory()->create(['status' => TopUpCardStatus::Pending]);
        $active = TopUpCard::factory()->create(['status' => TopUpCardStatus::Active]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/agent-assign')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TopUpCards/AgentAssign')
                ->has('cards.data', 1)
                ->where('cards.data.0.id', $active->id)
                ->where('filters.status', ''));

        $this->assertNotSame($pending->id, $active->id);
    }

    public function test_pending_generated_cards_can_be_imported_as_active(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $batch = Batch::query()->create([
            'batch_no' => 'IMPORT-BATCH-0001',
            'total_value' => 1000,
            'quantity' => 1,
            'status' => 'active',
            'expires_at' => now()->addDays(30)->toDateString(),
            'metadata' => [
                'items' => [
                    ['agent_cd' => '88', 'amount' => 1000, 'quantity' => 1],
                ],
            ],
        ]);
        $card = TopUpCard::factory()->create([
            'serial_no' => 'TOPUP-IMPORT-0001',
            'status' => TopUpCardStatus::Pending,
            'batch_id' => $batch->id,
        ]);

        $csv = "serial_no,pin,amount,expires_at,status\nTOPUP-IMPORT-0001,1234,1000,{$card->expires_at->toDateString()},pending\n";

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/agents/import', [
                'file' => UploadedFile::fake()->createWithContent('cards.csv', $csv),
                'return' => 'assign',
            ])
            ->assertRedirect('/top-up-cards/agent-assign');

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
}
