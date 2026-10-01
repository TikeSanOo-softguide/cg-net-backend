<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerHealthScanType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Jobs\GenerateLedgerHealthSnapshot;
use App\Models\Admin;
use App\Models\LedgerHealthSnapshot;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Services\Reports\LedgerHealthReportService;
use App\Support\AppSetting;
use App\Support\LedgerHealthWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LedgerHealthScanTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_scan_is_selected_by_default(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/reports/ledger-health')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page->component('Reports/LedgerHealth/Index')->where('scanType', 'daily'),
            );
    }

    public function test_index_filters_snapshots_by_scan_type(): void
    {
        $admin = Admin::factory()->create();

        $full = LedgerHealthSnapshot::query()->create([
            'requested_by' => $admin->id,
            'type' => LedgerHealthScanType::Full,
            'status' => 'completed',
            'results' => [
                'health' => [
                    'wallets' => 1,
                    'entries' => 1,
                    'balance_mismatches' => 0,
                    'completed_without_entry' => 0,
                    'unbalanced_transactions' => 0,
                    'duplicate_entry_transactions' => 0,
                    'source_mismatches' => 0,
                ],
                'balanceMismatches' => [],
                'missingEntries' => [],
                'sourceMismatches' => [],
            ],
            'checked_at' => now()->subHour(),
        ]);

        $daily = LedgerHealthSnapshot::query()->create([
            'requested_by' => null,
            'type' => LedgerHealthScanType::Daily,
            'window_start' => now()->subDay()->setTime(16, 0),
            'window_end' => now()->setTime(16, 0),
            'status' => 'completed',
            'results' => [
                'health' => [
                    'wallets' => 1,
                    'entries' => 1,
                    'balance_mismatches' => 0,
                    'completed_without_entry' => 0,
                    'unbalanced_transactions' => 0,
                    'duplicate_entry_transactions' => 0,
                    'source_mismatches' => 0,
                ],
                'balanceMismatches' => [],
                'missingEntries' => [],
                'sourceMismatches' => [],
            ],
            'checked_at' => now(),
        ]);

        $manual = LedgerHealthSnapshot::query()->create([
            'requested_by' => $admin->id,
            'type' => LedgerHealthScanType::Manual,
            'window_start' => now()->subDays(3),
            'window_end' => now()->subDay(),
            'status' => 'completed',
            'results' => [
                'health' => [
                    'wallets' => 1,
                    'entries' => 1,
                    'balance_mismatches' => 0,
                    'completed_without_entry' => 0,
                    'unbalanced_transactions' => 0,
                    'duplicate_entry_transactions' => 0,
                    'source_mismatches' => 0,
                ],
                'balanceMismatches' => [],
                'missingEntries' => [],
                'sourceMismatches' => [],
            ],
            'checked_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($admin, 'web')
            ->get('/reports/ledger-health?type=full')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Reports/LedgerHealth/Index')
                    ->where('scanType', 'full')
                    ->where('dailyScanEnabled', true)
                    ->where('snapshotId', $full->id)
                    ->has('snapshots', 1)
                    ->where('snapshots.0.id', $full->id)
                    ->where('snapshots.0.type', 'full'),
            );

        $this->actingAs($admin, 'web')
            ->get('/reports/ledger-health?type=daily')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Reports/LedgerHealth/Index')
                    ->where('scanType', 'daily')
                    ->where('snapshotId', $daily->id)
                    ->has('snapshots', 1)
                    ->where('snapshots.0.id', $daily->id)
                    ->where('snapshots.0.type', 'daily'),
            );

        $this->actingAs($admin, 'web')
            ->get('/reports/ledger-health?type=manual')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Reports/LedgerHealth/Index')
                    ->where('scanType', 'manual')
                    ->where('snapshotId', $manual->id)
                    ->has('snapshots', 1)
                    ->where('snapshots.0.id', $manual->id)
                    ->where('snapshots.0.type', 'manual'),
            );
    }

    public function test_manual_check_queues_full_scan(): void
    {
        Queue::fake();
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'web')->post('/reports/ledger-health/check', ['type' => 'full']);

        $snapshot = LedgerHealthSnapshot::query()->latest('id')->first();

        $this->assertNotNull($snapshot);
        $response->assertRedirect(
            route('reports.ledger-health.index', [
                'type' => 'full',
                'snapshot' => $snapshot->id,
            ]),
        );
        $this->assertSame(LedgerHealthScanType::Full, $snapshot->type);
        $this->assertSame('queued', $snapshot->status);
        $this->assertNull($snapshot->window_start);
        $this->assertNull($snapshot->window_end);

        Queue::assertPushed(
            GenerateLedgerHealthSnapshot::class,
            fn(GenerateLedgerHealthSnapshot $job): bool => $job->snapshotId === $snapshot->id,
        );
    }

    public function test_manual_range_check_queues_windowed_scan(): void
    {
        Queue::fake();
        config(['app.timezone' => 'Asia/Yangon']);
        $admin = Admin::factory()->create();

        $from = '2026-09-01T10:00:00';
        $to = '2026-09-05T18:30:00';

        $response = $this->actingAs($admin, 'web')->post('/reports/ledger-health/check', [
            'type' => 'manual',
            'from' => $from,
            'to' => $to,
        ]);

        $snapshot = LedgerHealthSnapshot::query()->latest('id')->first();

        $this->assertNotNull($snapshot);
        $response->assertRedirect(
            route('reports.ledger-health.index', [
                'type' => 'manual',
                'snapshot' => $snapshot->id,
            ]),
        );
        $this->assertSame(LedgerHealthScanType::Manual, $snapshot->type);
        $this->assertTrue($snapshot->window_start?->equalTo(Carbon::parse($from, 'Asia/Yangon')->utc()));
        $this->assertTrue($snapshot->window_end?->equalTo(Carbon::parse($to, 'Asia/Yangon')->utc()));

        Queue::assertPushed(GenerateLedgerHealthSnapshot::class);
    }

    public function test_manual_range_rejects_invalid_window(): void
    {
        Queue::fake();
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->from('/reports/ledger-health?type=manual')
            ->post('/reports/ledger-health/check', [
                'type' => 'manual',
                'from' => '2026-09-01T10:00:00',
                'to' => '2026-08-01T10:00:00',
            ])
            ->assertRedirect('/reports/ledger-health?type=manual')
            ->assertSessionHasErrors('to');

        $this->actingAs($admin, 'web')
            ->from('/reports/ledger-health?type=manual')
            ->post('/reports/ledger-health/check', [
                'type' => 'manual',
                'from' => '2026-08-01T10:00:00',
                'to' => '2026-09-15T10:00:00',
            ])
            ->assertRedirect('/reports/ledger-health?type=manual')
            ->assertSessionHasErrors('to');

        Queue::assertNothingPushed();
    }

    public function test_daily_command_queues_windowed_scan(): void
    {
        Queue::fake();
        config(['app.timezone' => 'Asia/Yangon']);
        Carbon::setTestNow(Carbon::parse('2026-10-01 16:00:00', 'Asia/Yangon'));

        $this->artisan('ledger-health:daily --date=2026-10-01')->assertSuccessful();

        $snapshot = LedgerHealthSnapshot::query()->latest('id')->first();
        [$expectedStart, $expectedEnd] = LedgerHealthWindow::forCloseDate('2026-10-01');

        $this->assertNotNull($snapshot);
        $this->assertSame(LedgerHealthScanType::Daily, $snapshot->type);
        $this->assertSame('queued', $snapshot->status);
        $this->assertTrue($snapshot->window_start?->equalTo($expectedStart));
        $this->assertTrue($snapshot->window_end?->equalTo($expectedEnd));

        Queue::assertPushed(GenerateLedgerHealthSnapshot::class);
    }

    public function test_daily_command_skips_when_disabled(): void
    {
        Queue::fake();
        AppSetting::put(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, false);

        $this->artisan('ledger-health:daily --date=2026-10-01')->assertSuccessful();

        $this->assertSame(0, LedgerHealthSnapshot::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_daily_scan_toggle_persists_setting(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/reports/ledger-health/daily-scan', ['enabled' => false])
            ->assertRedirect(route('reports.ledger-health.index', ['type' => 'daily']));

        $this->assertFalse(AppSetting::boolean(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true));

        $this->actingAs($admin, 'web')
            ->get('/reports/ledger-health?type=daily')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('dailyScanEnabled', false));

        $this->actingAs($admin, 'web')
            ->post('/reports/ledger-health/daily-scan', ['enabled' => true])
            ->assertRedirect(route('reports.ledger-health.index', ['type' => 'daily']));

        $this->assertTrue(AppSetting::boolean(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true));
    }

    public function test_daily_window_excludes_activity_outside_range(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        [$windowStart, $windowEnd] = LedgerHealthWindow::forCloseDate('2026-10-01');

        Carbon::setTestNow($windowStart->clone()->subHour());
        $outside = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 100,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'outside-window',
        );
        $outside->entries()->delete();

        Carbon::setTestNow($windowStart->clone()->addHour());
        $inside = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 200,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'inside-window',
        );
        $inside->entries()->delete();

        Carbon::setTestNow($windowEnd);

        $report = app(LedgerHealthReportService::class)->generate($windowStart, $windowEnd);

        $this->assertSame(1, $report['health']['completed_without_entry']);
        $this->assertTrue(
            collect($report['missingEntries'])->contains(
                fn(array $row): bool => $row['transaction_no'] === $inside->transaction_no,
            ),
        );
        $this->assertFalse(
            collect($report['missingEntries'])->contains(
                fn(array $row): bool => $row['transaction_no'] === $outside->transaction_no,
            ),
        );
        $this->assertNotNull($report['window']);
    }
}
