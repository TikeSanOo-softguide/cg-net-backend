<?php

namespace App\Console\Commands;

use App\Enums\LedgerHealthScanType;
use App\Jobs\GenerateLedgerHealthSnapshot;
use App\Models\LedgerHealthSnapshot;
use App\Support\AppSetting;
use App\Support\LedgerHealthWindow;
use Illuminate\Console\Command;

class RunDailyLedgerHealthScan extends Command
{
    protected $signature = 'ledger-health:daily {--date= : Business close date Y-m-d in the application timezone}';

    protected $description = 'Queue a daily ledger health scan for yesterday 4pm to today 4pm in the application timezone.';

    public function handle(): int
    {
        if (!AppSetting::boolean(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true)) {
            $this->warn('Skipped: automatic daily ledger health scan is disabled.');

            return self::SUCCESS;
        }

        $activeRun = LedgerHealthSnapshot::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($activeRun) {
            $this->warn("Skipped: snapshot #{$activeRun->id} is already {$activeRun->status}.");

            return self::SUCCESS;
        }

        $closeDate = $this->option('date');
        $closeDate = is_string($closeDate) && $closeDate !== '' ? $closeDate : null;

        [$windowStart, $windowEnd] = LedgerHealthWindow::forCloseDate($closeDate);

        $snapshot = LedgerHealthSnapshot::query()->create([
            'requested_by' => null,
            'type' => LedgerHealthScanType::Daily,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'status' => 'queued',
        ]);

        GenerateLedgerHealthSnapshot::dispatch($snapshot->id);

        $this->info(
            sprintf(
                'Queued daily ledger health snapshot #%d for %s → %s.',
                $snapshot->id,
                $windowStart->timezone(config('app.timezone'))->toDateTimeString(),
                $windowEnd->timezone(config('app.timezone'))->toDateTimeString(),
            ),
        );

        return self::SUCCESS;
    }
}
