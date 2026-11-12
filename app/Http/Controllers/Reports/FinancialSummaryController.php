<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateFinancialSummarySnapshot;
use App\Models\FinancialSummarySnapshot;
use App\Services\AdminNotification\AdminNotificationReadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FinancialSummaryController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $snapshots = FinancialSummarySnapshot::query()
            ->where('status', 'completed')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'checked_at']);
        $unreadSnapshotIds = $readStatus->unreadReferenceIds(
            (int) $request->user()->getAuthIdentifier(),
            'financial_summary_snapshot',
            $snapshots->pluck('id')->all(),
        );

        $selectedSnapshotId = $request->integer('snapshot');
        $selectedSnapshot = $snapshots->firstWhere('id', $selectedSnapshotId) ?? $snapshots->first();
        $snapshot = $selectedSnapshot
            ? FinancialSummarySnapshot::query()->where('status', 'completed')->find($selectedSnapshot->id)
            : null;
        $latestRun = FinancialSummarySnapshot::query()->latest('id')->first();
        $activeRun = FinancialSummarySnapshot::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();
        $run = $activeRun ?? $latestRun;

        return Inertia::render('Reports/FinancialSummary/Index', [
            'snapshot' => $snapshot
                ? [...$snapshot->results ?? [], 'checked_at' => $snapshot->checked_at?->toISOString()]
                : null,
            'snapshotId' => $snapshot?->id,
            'snapshots' => $snapshots
                ->map(
                    fn(FinancialSummarySnapshot $item): array => [
                        'id' => $item->id,
                        'checked_at' => $item->checked_at?->toISOString(),
                        'notification_unread' => in_array($item->id, $unreadSnapshotIds, true),
                    ],
                )
                ->values(),
            'run' => $run
                ? [
                    'id' => $run->id,
                    'status' => $run->status,
                    'requested_at' => $run->created_at?->toISOString(),
                ]
                : null,
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        $activeRun = FinancialSummarySnapshot::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($activeRun) {
            return redirect()
                ->route('reports.financial-summary.index', ['snapshot' => $activeRun->id])
                ->with('success', 'financial_summary_report.already_running');
        }

        $snapshot = FinancialSummarySnapshot::query()->create([
            'requested_by' => $request->user()->getAuthIdentifier(),
            'status' => 'queued',
        ]);

        try {
            GenerateFinancialSummarySnapshot::dispatch($snapshot->id);
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);
            Log::error('Failed to dispatch financial summary snapshot.', [
                'snapshot_id' => $snapshot->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return redirect()
            ->route('reports.financial-summary.index', ['snapshot' => $snapshot->id])
            ->with('success', 'financial_summary_report.check_queued');
    }
}
