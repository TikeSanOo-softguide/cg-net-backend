<?php

namespace App\Jobs;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Events\FinancialSummaryFinished;
use App\Models\Admin;
use App\Models\FinancialSummarySnapshot;
use App\Services\Reports\FinancialSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateFinancialSummarySnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 540;

    public function __construct(public readonly int $snapshotId)
    {
        $this->onConnection('redis')->onQueue('reports');
    }

    public function handle(FinancialSummaryService $summary): void
    {
        $snapshot = FinancialSummarySnapshot::query()->find($this->snapshotId);

        if (!$snapshot || $snapshot->status !== 'queued') {
            return;
        }

        $snapshot->update(['status' => 'running']);

        try {
            $results = $summary->generate();
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            Log::error('Financial summary snapshot failed.', [
                'snapshot_id' => $snapshot->id,
                'exception' => $exception->getMessage(),
            ]);
            $this->logActivity($snapshot);
            $this->notifyAdmins($snapshot, completed: false);

            throw $exception;
        }

        $snapshot->update([
            'status' => 'completed',
            'results' => $results,
            'error' => null,
            'checked_at' => now(),
        ]);
        $this->logActivity($snapshot);
        $this->notifyAdmins($snapshot, completed: true);
    }

    public function failed(?Throwable $exception): void
    {
        $updated = FinancialSummarySnapshot::query()
            ->whereKey($this->snapshotId)
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'status' => 'failed',
                'error' => $exception?->getMessage(),
            ]);

        if ($updated > 0) {
            $snapshot = FinancialSummarySnapshot::query()->find($this->snapshotId);
            if ($snapshot) {
                $this->notifyAdmins($snapshot, completed: false);
            }

            Log::error('Financial summary snapshot failed.', [
                'snapshot_id' => $this->snapshotId,
                'exception' => $exception?->getMessage(),
            ]);
        }
    }

    private function notifyAdmins(FinancialSummarySnapshot $snapshot, bool $completed): void
    {
        try {
            FinancialSummaryFinished::dispatch($snapshot->id, $completed);
        } catch (Throwable $exception) {
            Log::error('Failed to notify admins about a financial summary.', [
                'snapshot_id' => $snapshot->id,
                'status' => $snapshot->status,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function logActivity(FinancialSummarySnapshot $snapshot): void
    {
        if ($snapshot->requested_by === null) {
            return;
        }

        $admin = Admin::query()->find($snapshot->requested_by);
        if (!$admin) {
            return;
        }

        activity(ActivityLogName::Reports->value)
            ->causedBy($admin)
            ->performedOn($snapshot)
            ->event(ActivityLogEvent::from($snapshot->status)->value)
            ->withProperties([
                'status' => $snapshot->status,
                'checked_at' => $snapshot->checked_at?->toISOString(),
            ])
            ->log('financial_summary_generated');
    }
}
