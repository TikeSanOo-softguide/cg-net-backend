<?php

namespace App\Jobs;

use App\Events\LedgerHealthScanFinished;
use App\Models\LedgerHealthSnapshot;
use App\Services\Reports\LedgerHealthReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateLedgerHealthSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 540;

    public function __construct(public readonly int $snapshotId)
    {
        $this->onConnection('redis')->onQueue('ledger-reports');
    }

    public function handle(LedgerHealthReportService $report): void
    {
        $snapshot = LedgerHealthSnapshot::query()->find($this->snapshotId);

        if (! $snapshot || $snapshot->status !== 'queued') {
            return;
        }

        $snapshot->update(['status' => 'running']);

        try {
            $usesWindow = $snapshot->type->usesWindow();
            $windowStart = $usesWindow ? $snapshot->window_start : null;
            $windowEnd = $usesWindow ? $snapshot->window_end : null;
            $results = $report->generate($windowStart, $windowEnd);

            $snapshot->update([
                'status' => 'completed',
                'results' => $results,
                'error' => null,
                'checked_at' => now(),
            ]);
            $this->notifyAdmins($snapshot, $this->hasDiscrepancies($results));
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            Log::error('Ledger health snapshot failed.', [
                'snapshot_id' => $snapshot->id,
                'exception' => $exception->getMessage(),
            ]);

            $this->notifyAdmins($snapshot, hasDiscrepancies: false);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $updated = LedgerHealthSnapshot::query()
            ->whereKey($this->snapshotId)
            ->where('status', '!=', 'failed')
            ->update([
                'status' => 'failed',
                'error' => $exception?->getMessage(),
            ]);

        if ($updated > 0) {
            $snapshot = LedgerHealthSnapshot::query()->find($this->snapshotId);
            if ($snapshot) {
                $this->notifyAdmins($snapshot, hasDiscrepancies: false);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $results
     */
    private function hasDiscrepancies(array $results): bool
    {
        $health = $results['health'] ?? [];

        foreach ([
            'balance_mismatches',
            'completed_without_entry',
            'unbalanced_transactions',
            'duplicate_entry_transactions',
            'source_mismatches',
        ] as $counter) {
            if (($health[$counter] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function notifyAdmins(LedgerHealthSnapshot $snapshot, bool $hasDiscrepancies): void
    {
        try {
            LedgerHealthScanFinished::dispatch(
                $snapshot->id,
                $snapshot->type,
                $snapshot->status === 'completed',
                $hasDiscrepancies,
            );
        } catch (Throwable $exception) {
            Log::error('Failed to notify admins about a ledger health scan.', [
                'snapshot_id' => $snapshot->id,
                'status' => $snapshot->status,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
