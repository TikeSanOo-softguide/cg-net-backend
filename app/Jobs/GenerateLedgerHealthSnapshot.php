<?php

namespace App\Jobs;

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

            $snapshot->update([
                'status' => 'completed',
                'results' => $report->generate($windowStart, $windowEnd),
                'error' => null,
                'checked_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            Log::error('Ledger health snapshot failed.', [
                'snapshot_id' => $snapshot->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        LedgerHealthSnapshot::query()
            ->whereKey($this->snapshotId)
            ->update([
                'status' => 'failed',
                'error' => $exception?->getMessage(),
            ]);
    }
}
