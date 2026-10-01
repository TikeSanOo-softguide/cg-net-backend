<?php

namespace App\Http\Controllers\Reports;

use App\Enums\LedgerHealthScanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RunLedgerHealthCheckRequest;
use App\Http\Requests\Reports\UpdateLedgerHealthDailyScanRequest;
use App\Jobs\GenerateLedgerHealthSnapshot;
use App\Models\LedgerHealthSnapshot;
use App\Support\AppSetting;
use App\Support\LedgerHealthWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LedgerHealthReportController extends Controller
{
    public function index(Request $request): Response
    {
        $scanType = $this->resolveScanType($request->string('type')->toString());
        [$defaultWindowStart, $defaultWindowEnd] = LedgerHealthWindow::forCloseDate();

        $activeRun = LedgerHealthSnapshot::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        $snapshots = LedgerHealthSnapshot::query()
            ->where('status', 'completed')
            ->where('type', $scanType)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'type', 'checked_at', 'window_start', 'window_end']);

        $selectedSnapshotId = $request->integer('snapshot');
        $selectedSnapshotId = $snapshots->contains('id', $selectedSnapshotId)
            ? $selectedSnapshotId
            : $snapshots->first()?->id;

        $snapshot = $selectedSnapshotId
            ? LedgerHealthSnapshot::query()->where('status', 'completed')->find($selectedSnapshotId)
            : null;

        if ($snapshot && $snapshot->type !== $scanType) {
            $snapshot = null;
            $selectedSnapshotId = null;
        }

        return Inertia::render('Reports/LedgerHealth/Index', [
            'scanType' => $scanType->value,
            'dailyScanEnabled' => AppSetting::boolean(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true),
            'defaultWindow' => [
                'start' => $defaultWindowStart->timezone(config('app.timezone'))->format('Y-m-d\TH:i:s'),
                'end' => $defaultWindowEnd->timezone(config('app.timezone'))->format('Y-m-d\TH:i:s'),
            ],
            'snapshot' => $snapshot
                ? [
                    ...$snapshot->results ?? [],
                    'checked_at' => $snapshot->checked_at?->toISOString(),
                    'type' => $snapshot->type->value,
                    'window_start' => $snapshot->window_start?->toISOString(),
                    'window_end' => $snapshot->window_end?->toISOString(),
                ]
                : null,
            'snapshotId' => $snapshot?->id,
            'snapshots' => $snapshots
                ->map(
                    fn(LedgerHealthSnapshot $item): array => [
                        'id' => $item->id,
                        'type' => $item->type->value,
                        'checked_at' => $item->checked_at?->toISOString(),
                        'window_start' => $item->window_start?->toISOString(),
                        'window_end' => $item->window_end?->toISOString(),
                    ],
                )
                ->values(),
            'run' => $activeRun
                ? [
                    'id' => $activeRun->id,
                    'type' => $activeRun->type->value,
                    'status' => $activeRun->status,
                    'requested_at' => $activeRun->created_at?->toISOString(),
                ]
                : null,
        ]);
    }

    public function check(RunLedgerHealthCheckRequest $request): RedirectResponse
    {
        $scanType = LedgerHealthScanType::from($request->string('type')->toString());

        $activeRun = LedgerHealthSnapshot::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($activeRun) {
            return redirect()
                ->route('reports.ledger-health.index', [
                    'type' => $activeRun->type->value,
                    'snapshot' => $activeRun->id,
                ])
                ->with('success', 'ledger_health_report.already_running');
        }

        $windowStart = null;
        $windowEnd = null;

        if ($scanType === LedgerHealthScanType::Manual) {
            $timezone = (string) config('app.timezone', 'UTC');
            $windowStart = Carbon::parse($request->string('from')->toString(), $timezone)->utc();
            $windowEnd = Carbon::parse($request->string('to')->toString(), $timezone)->utc();
        }

        $snapshot = LedgerHealthSnapshot::query()->create([
            'requested_by' => $request->user()->getAuthIdentifier(),
            'type' => $scanType,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'status' => 'queued',
        ]);

        GenerateLedgerHealthSnapshot::dispatch($snapshot->id);

        return redirect()
            ->route('reports.ledger-health.index', [
                'type' => $scanType->value,
                'snapshot' => $snapshot->id,
            ])
            ->with('success', 'ledger_health_report.check_queued');
    }

    public function updateDailyScan(UpdateLedgerHealthDailyScanRequest $request): RedirectResponse
    {
        AppSetting::put(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, $request->boolean('enabled'));

        return redirect()
            ->route('reports.ledger-health.index', ['type' => LedgerHealthScanType::Daily->value])
            ->with(
                'success',
                $request->boolean('enabled')
                    ? 'ledger_health_report.daily_scan_enabled'
                    : 'ledger_health_report.daily_scan_disabled',
            );
    }

    private function resolveScanType(string $value): LedgerHealthScanType
    {
        return LedgerHealthScanType::tryFrom($value) ?? LedgerHealthScanType::Daily;
    }
}
