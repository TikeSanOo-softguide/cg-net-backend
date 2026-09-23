<?php

namespace Tests\Feature;

use App\Enums\TopUpCardStatus;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Models\User;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TopUpCardManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->actingAs($admin, 'web')
            ->get('/top-up-cards/batch')
            ->assertForbidden();

        $this->actingAs($admin, 'web')
            ->get('/top-up-cards/redeem-history')
            ->assertForbidden();

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

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/batch')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('TopUpCards/Generate'));

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/batch', [
                'amounts' => [
                    ['value' => 3000, 'quantity' => 5],
                    ['value' => 10000, 'quantity' => 2],
                ],
                'expires_at' => now()->addDays(90)->toDateString(),
            ])
            ->assertRedirect('/top-up-cards/batch');

        $cards = TopUpCard::query()->get();

        $this->assertCount(7, $cards);
        $this->assertSame(5, TopUpCard::query()->where('amount', 3000)->count());
        $this->assertSame(2, TopUpCard::query()->where('amount', 10000)->count());
        $this->assertCount(7, $cards->pluck('serial_no')->unique());
        $this->assertTrue($cards->every(fn (TopUpCard $card) => str_starts_with($card->serial_no, 'TOPUP-')));
        $this->assertTrue($cards->every(fn (TopUpCard $card) => $card->status === TopUpCardStatus::Valid));
        $this->assertTrue($cards->every(fn (TopUpCard $card) => Hash::isHashed($card->pin)));
        $this->assertDatabaseHas('activity_log', [
            'description' => 'top_up_cards_generated',
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
            'status' => TopUpCardStatus::Valid,
        ]);
        TopUpCard::factory()->create([
            'serial_no' => 'TOPUP-DDDD-EEEE-FFFF',
            'amount' => 1000,
            'status' => TopUpCardStatus::Invalid,
        ]);

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/batch?search=AAAA&status=valid&amount=5000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TopUpCards/Generate')
                ->has('cards.data', 1)
                ->where('cards.data.0.id', $match->id));
    }

    public function test_admins_can_void_a_valid_card(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $card = TopUpCard::factory()->create();

        $this->actingAs($actor, 'web')
            ->patch('/top-up-cards/'.$card->id.'/void')
            ->assertRedirect();

        $this->assertSame(TopUpCardStatus::Invalid, $card->fresh()->status);
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

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/export')
            ->assertNotFound();

        $this->actingAs($actor, 'web')
            ->post('/top-up-cards/batch', [
                'amounts' => [['value' => 1000, 'quantity' => 1]],
                'expires_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertRedirect('/top-up-cards/batch');

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
            ->get('/top-up-cards/agents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TopUpCards/Agent')
                ->has('cards', 1)
                ->where('cards.0.id', $active->id)
                ->where('filters.status', ''));

        $this->assertNotSame($pending->id, $active->id);
    }

    public function test_pending_generated_cards_can_be_imported_as_active(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $batch = Batch::query()->create([
            'batch_no' => 'IMPORT-BATCH-0001',
            'amount' => 1000,
            'quantity' => 1,
            'status' => 'active',
            'expires_at' => now()->addDays(30)->toDateString(),
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
            ])
            ->assertRedirect('/top-up-cards/agents?batch='.$batch->id);

        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
    }

    public function test_admins_can_view_redeem_history(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $customer = User::factory()->create(['name' => 'Aung Aung', 'phone' => '09111111111']);
        $redeemed = TopUpCard::factory()->redeemed($customer)->create([
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('TopUpCards/History')
                ->has('cards.data', 1)
                ->where('cards.data.0.id', $redeemed->id)
                ->where('stats.total', 1)
                ->where('recent.0.id', $redeemed->id));

        $this->actingAs($actor, 'web')
            ->get('/top-up-cards/redeem-history?search=Aung')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TopUpCards/History')
                ->has('cards.data', 1));
    }
}
