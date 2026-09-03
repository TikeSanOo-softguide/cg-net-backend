<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\BroadbandAccountStatus;
use App\Enums\ChangePlanStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\BroadbandAccount;
use App\Models\ChangePlanRequest;
use App\Models\CustomerPackage;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\Payment;
use App\Models\RelocationRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const REQUEST_MODELS = [
        'installation' => InstallationApplication::class,
        'failure' => FailureReport::class,
        'relocation' => RelocationRequest::class,
        'change_plan' => ChangePlanRequest::class,
    ];

    public function __invoke(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'stats' => $this->stats(),
            'chart' => $this->chartSeries(),
            'regionChart' => $this->regionChart(),
            'requestTypeChart' => $this->requestTypeChart(),
            'recentRequests' => $this->recentRequests(),
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'distinct', 'regex:/^(installation|failure|relocation|change_plan)-\d+$/'],
        ])['ids'];

        $deleted = 0;
        $skipped = false;

        foreach ($ids as $composite) {
            [$type, $id] = explode('-', $composite, 2);
            $item = self::REQUEST_MODELS[$type]::query()->find($id);

            if ($item === null) {
                $skipped = true;

                continue;
            }

            $item->delete();
            activity('service-requests')->causedBy($request->user())->performedOn($item)->event('deleted')->log($type.'_deleted');
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->withErrors(['delete' => 'common.bulk_delete_failed']);
        }

        $redirect = redirect()->route('dashboard')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deleted);

        if ($skipped) {
            return $redirect->withErrors(['delete' => 'dashboard.bulk_delete_partial']);
        }

        return $redirect;
    }

    /**
     * @return array{total_customers: int, active_broadband_accounts: int, active_packages: int, todays_revenue: string, pending_requests: int}
     */
    private function stats(): array
    {
        $pending = InstallationApplication::query()->where('status', ReviewStatus::UnderReview)->count()
            + FailureReport::query()->where('status', ReviewStatus::UnderReview)->count()
            + RelocationRequest::query()->where('status', ReviewStatus::UnderReview)->count()
            + ChangePlanRequest::query()->where('status', ChangePlanStatus::UnderReview)->count();

        return [
            'total_customers' => User::query()->count(),
            'active_broadband_accounts' => BroadbandAccount::query()
                ->where('status', BroadbandAccountStatus::Active)
                ->count(),
            'active_packages' => CustomerPackage::query()
                ->where('status', CustomerPackageStatus::Active)
                ->count(),
            'todays_revenue' => number_format((float) Payment::query()
                ->where('status', PaymentStatus::Paid)
                ->whereDate('paid_at', today())
                ->sum('amount'), 2, '.', ''),
            'pending_requests' => $pending,
        ];
    }

    /**
     * @return list<array{date: string, revenue: float, signups: int}>
     */
    private function chartSeries(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $revenue = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['paid_at', 'amount'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->toDateString())
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $signups = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (User $user) => $user->created_at->toDateString())
            ->map(fn (Collection $rows) => $rows->count());

        return collect(CarbonPeriod::create($start, $end))
            ->map(function (Carbon $day) use ($revenue, $signups) {
                $key = $day->toDateString();

                return [
                    'date' => $key,
                    'revenue' => (float) ($revenue[$key] ?? 0),
                    'signups' => (int) ($signups[$key] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int|null, name_en: string, name_zh: string, name_my: string, value: int}>
     */
    private function regionChart(): array
    {
        $counts = InstallationApplication::query()
            ->join('areas', 'areas.id', '=', 'installation_applications.area_id')
            ->join('regions', 'regions.id', '=', 'areas.region_id')
            ->whereNull('areas.deleted_at')
            ->whereNull('regions.deleted_at')
            ->select([
                'regions.id',
                'regions.name_en',
                'regions.name_zh',
                'regions.name_my',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('regions.id', 'regions.name_en', 'regions.name_zh', 'regions.name_my')
            ->orderByDesc('total')
            ->get();

        $toSlice = static fn ($row): array => [
            'id' => (int) $row->id,
            'name_en' => (string) $row->name_en,
            'name_zh' => (string) $row->name_zh,
            'name_my' => (string) $row->name_my,
            'value' => (int) $row->total,
        ];

        if ($counts->count() <= 5) {
            return $counts->map($toSlice)->values()->all();
        }

        $items = $counts->take(4)->map($toSlice)->values()->all();
        $otherCount = (int) $counts->slice(4)->sum('total');

        if ($otherCount > 0) {
            $items[] = [
                'id' => null,
                'name_en' => 'Other',
                'name_zh' => 'Other',
                'name_my' => 'Other',
                'value' => $otherCount,
            ];
        }

        return $items;
    }

    /**
     * @return array{change: int|null, items: list<array{type: string, value: int, percent: int}>}
     */
    private function requestTypeChart(): array
    {
        $items = collect(self::REQUEST_MODELS)
            ->map(fn (string $class, string $type) => [
                'type' => $type,
                'value' => $class::query()->count(),
            ])
            ->sortByDesc('value')
            ->values();

        $total = (int) $items->sum('value');

        return [
            'change' => $this->requestVolumeChange(),
            'items' => $items
                ->map(fn (array $row) => [
                    'type' => $row['type'],
                    'value' => $row['value'],
                    'percent' => $total > 0 ? (int) round($row['value'] / $total * 100) : 0,
                ])
                ->all(),
        ];
    }

    private function requestVolumeChange(): ?int
    {
        $current = $this->requestCountBetween(now()->subDays(29)->startOfDay(), now()->endOfDay());
        $previous = $this->requestCountBetween(now()->subDays(59)->startOfDay(), now()->subDays(30)->endOfDay());

        if ($previous === 0) {
            return $current > 0 ? 100 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function requestCountBetween(Carbon $start, Carbon $end): int
    {
        return (int) collect(self::REQUEST_MODELS)
            ->sum(fn (string $class) => $class::query()->whereBetween('created_at', [$start, $end])->count());
    }

    /**
     * @return list<array{id: string, type: string, customer: string, status: string, created_at: string}>
     */
    private function recentRequests(): array
    {
        $installs = InstallationApplication::query()
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (InstallationApplication $row) => [
                'id' => 'installation-'.$row->id,
                'type' => 'installation',
                'customer' => $row->user?->name ?? '—',
                'status' => $row->status->value,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        $failures = FailureReport::query()
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (FailureReport $row) => [
                'id' => 'failure-'.$row->id,
                'type' => 'failure',
                'customer' => $row->user?->name ?? '—',
                'status' => $row->status->value,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        $relocations = RelocationRequest::query()
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (RelocationRequest $row) => [
                'id' => 'relocation-'.$row->id,
                'type' => 'relocation',
                'customer' => $row->user?->name ?? '—',
                'status' => $row->status->value,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        $changes = ChangePlanRequest::query()
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (ChangePlanRequest $row) => [
                'id' => 'change_plan-'.$row->id,
                'type' => 'change_plan',
                'customer' => $row->user?->name ?? '—',
                'status' => $row->status->value,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return $installs
            ->concat($failures)
            ->concat($relocations)
            ->concat($changes)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->all();
    }
}
