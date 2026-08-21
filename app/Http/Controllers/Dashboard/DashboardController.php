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
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'stats' => $this->stats(),
            'chart' => $this->chartSeries(),
            'recentRequests' => $this->recentRequests(),
        ]);
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
