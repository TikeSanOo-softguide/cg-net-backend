<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLedgerHealthSnapshot;
use App\Models\LedgerHealthSnapshot;
use App\Support\ReturnTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerHealthReportController extends Controller
{
    public function index(Request $request): Response
    {
        $latestRun = LedgerHealthSnapshot::query()->latest('id')->first();
        $snapshots = LedgerHealthSnapshot::query()
            ->where('status', 'completed')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'checked_at']);
        $selectedSnapshotId = $request->integer('snapshot');
        $selectedSnapshotId = $snapshots->contains('id', $selectedSnapshotId)
            ? $selectedSnapshotId
            : $snapshots->first()?->id;
        $snapshot = $selectedSnapshotId
            ? LedgerHealthSnapshot::query()->where('status', 'completed')->find($selectedSnapshotId)
            : null;

        // Deep links from this page should return here (customers + transactions).
        ReturnTo::remember($request, 'customers.show');
        ReturnTo::remember($request, 'transactions.index');
        ReturnTo::captureReferer($request, 'reports.ledger-health');

        return Inertia::render('Reports/LedgerHealth/Index', [
            'snapshot' => $snapshot
                ? [...$snapshot->results ?? [], 'checked_at' => $snapshot->checked_at?->toISOString()]
                : null,
            'snapshotId' => $snapshot?->id,
            'snapshots' => $snapshots
                ->map(
                    fn(LedgerHealthSnapshot $item): array => [
                        'id' => $item->id,
                        'checked_at' => $item->checked_at?->toISOString(),
                    ],
                )
                ->values(),
            'run' => $latestRun
                ? [
                    'id' => $latestRun->id,
                    'status' => $latestRun->status,
                    'requested_at' => $latestRun->created_at?->toISOString(),
                ]
                : null,
            ...ReturnTo::prop('reports.ledger-health', '/reports'),
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        $activeRun = LedgerHealthSnapshot::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($activeRun) {
            return redirect()
                ->route('reports.ledger-health.index', ['snapshot' => $activeRun->id])
                ->with('success', 'ledger_health_report.already_running');
        }

        $snapshot = LedgerHealthSnapshot::query()->create([
            'requested_by' => $request->user()->getAuthIdentifier(),
            'status' => 'queued',
        ]);

        GenerateLedgerHealthSnapshot::dispatch($snapshot->id);

        return redirect()
            ->route('reports.ledger-health.index', ['snapshot' => $snapshot->id])
            ->with('success', 'ledger_health_report.check_queued');
    }
}
