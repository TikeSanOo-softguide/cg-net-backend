<?php

namespace App\Http\Controllers\Reports;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Models\Package;
use App\Models\WalletTransaction;
use App\Support\PackageLabel;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingReportController extends Controller
{
    private const PAYMENT_TYPES = [
        LedgerTransactionType::Topup,
        LedgerTransactionType::FtthBill,
        LedgerTransactionType::WifiPackage,
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'mode' => ['nullable', Rule::in(['yearly', 'date_range'])],
            'year' => ['nullable', 'integer', 'between:2000,' . now()->year],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['successful', 'pending', 'failed', 'refunded'])],
            'method' => [
                'nullable',
                Rule::in([
                    LedgerTransactionType::Topup->value,
                    LedgerTransactionType::FtthBill->value,
                    LedgerTransactionType::WifiPackage->value,
                ]),
            ],
            'package' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $mode = $filters['mode'] ?? (isset($filters['from']) || isset($filters['to']) ? 'date_range' : 'yearly');
        $year = (int) ($filters['year'] ?? now()->year);
        [$fromDate, $toDate] = $this->resolveDateRange($filters, $mode, $year);
        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();

        $scoped = $this->filteredTransactions($filters, $fromDate, $toDate);
        $payments = (clone $scoped)->whereIn('type', self::PAYMENT_TYPES);

        // Collected points = completed top-up + FTTH bill + Wi-Fi package amounts.
        $collectedPoints = (int) (clone $payments)->where('status', LedgerTransactionStatus::Completed)->sum('amount');

        // Outstanding points = pending + processing payment amounts still in flight.
        $outstandingPoints = (int) (clone $payments)
            ->whereIn('status', [LedgerTransactionStatus::Pending, LedgerTransactionStatus::Processing])
            ->sum('amount');

        // Successful payments = number of completed payment transactions.
        $successfulPayments = (clone $payments)->where('status', LedgerTransactionStatus::Completed)->count();

        // Failed payments = number of failed payment transactions.
        $failedPayments = (clone $payments)->where('status', LedgerTransactionStatus::Failed)->count();

        // Refund points = completed refund amounts (refund type only, not payment types).
        $refundedPoints = (int) (clone $scoped)
            ->where('type', LedgerTransactionType::Refund)
            ->where('status', LedgerTransactionStatus::Completed)
            ->sum('amount');

        $trendStatus = $filters['status'] ?? 'successful';
        $trendTransactions = match ($trendStatus) {
            'pending' => (clone $scoped)
                ->whereIn('type', self::PAYMENT_TYPES)
                ->whereIn('status', [LedgerTransactionStatus::Pending, LedgerTransactionStatus::Processing]),
            'failed' => (clone $scoped)
                ->whereIn('type', self::PAYMENT_TYPES)
                ->where('status', LedgerTransactionStatus::Failed),
            'refunded' => (clone $scoped)
                ->where('type', LedgerTransactionType::Refund)
                ->where('status', LedgerTransactionStatus::Completed),
            default => (clone $payments)->where('status', LedgerTransactionStatus::Completed),
        };

        // Trend points use the selected status; refunds are counted from completed refund transactions.
        $dailyCollection = $trendTransactions
            ->selectRaw('DATE(created_at) as date, SUM(amount) as points')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $trend = $this->trendSeries($dailyCollection, $start, $end);
        $maxTrendPoints = max(1, (int) $trend->max('points'));
        $trend = $trend->map(
            fn(array $item) => [...$item, 'percentage' => round(($item['points'] / $maxTrendPoints) * 100, 1)],
        );

        $methodTotals = (clone $payments)
            ->where('status', LedgerTransactionStatus::Completed)
            ->selectRaw('type, SUM(amount) as points')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($row) {
                $type = $row->type instanceof LedgerTransactionType ? $row->type->value : (string) $row->type;

                return [$type => (int) $row->points];
            });

        $methods = collect(self::PAYMENT_TYPES)
            ->map(function (LedgerTransactionType $type) use ($methodTotals, $collectedPoints) {
                $points = (int) ($methodTotals[$type->value] ?? 0);

                return [
                    'name' => match ($type) {
                        LedgerTransactionType::Topup => 'Top-up',
                        LedgerTransactionType::FtthBill => 'FTTH bill',
                        LedgerTransactionType::WifiPackage => 'Wi-Fi package',
                    },
                    'points' => $points,
                    'percentage' => $collectedPoints > 0 ? round(($points / $collectedPoints) * 100, 1) : 0,
                ];
            })
            ->values();

        return Inertia::render('Reports/BillingReports/Index', [
            'filters' => [
                'mode' => $mode,
                'year' => (string) $year,
                'from' => $fromDate,
                'to' => $toDate,
                'status' => $filters['status'] ?? 'all',
                'method' => $filters['method'] ?? 'all',
                'package' => isset($filters['package']) ? (string) $filters['package'] : 'all',
            ],
            'years' => collect(range(now()->year, 2024))->map(
                fn(int $value) => [
                    'value' => (string) $value,
                    'label' => (string) $value,
                ],
            ),
            'summary' => [
                'collected_points' => $collectedPoints,
                'outstanding_points' => $outstandingPoints,
                'successful_payments' => $successfulPayments,
                'failed_payments' => $failedPayments,
                'refunded_points' => $refundedPoints,
            ],
            'trend' => $trend,
            'trendStatus' => $trendStatus,
            'trendHasData' => $dailyCollection->isNotEmpty(),
            'methods' => $methods,
            'paymentMethods' => [
                ['value' => LedgerTransactionType::Topup->value, 'label' => 'Top-up'],
                ['value' => LedgerTransactionType::FtthBill->value, 'label' => 'FTTH bill'],
                ['value' => LedgerTransactionType::WifiPackage->value, 'label' => 'Wi-Fi package'],
            ],
            'packages' => Package::query()
                ->with(['network:id,name_en,name_zh,name_my', 'speed:id,mbps', 'term:id,months'])
                ->orderBy('id')
                ->get()
                ->map(
                    fn(Package $package) => [
                        'value' => (string) $package->id,
                        'label' => PackageLabel::make($package, 'en') ?? 'Package #' . $package->id,
                    ],
                )
                ->values(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(array $filters, string $mode, int $year): array
    {
        if ($mode === 'yearly') {
            return [
                Carbon::create($year, 1, 1)->startOfDay()->toDateString(),
                Carbon::create($year, 12, 31)->endOfDay()->toDateString(),
            ];
        }

        $fromDate = isset($filters['from'])
            ? Carbon::parse($filters['from'])->toDateString()
            : now()->startOfMonth()->toDateString();
        $toDate = isset($filters['to']) ? Carbon::parse($filters['to'])->toDateString() : today()->toDateString();

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function filteredTransactions(array $filters, string $fromDate, string $toDate): Builder
    {
        return LedgerTransaction::query()
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->when($filters['method'] ?? null, fn(Builder $query, string $method) => $query->where('type', $method))
            ->when(
                $filters['package'] ?? null,
                fn(Builder $query, int|string $package) => $query->whereHas(
                    'packageOrder',
                    fn(Builder $packageQuery) => $packageQuery->where('package_id', $package),
                ),
            );
    }

    /**
     * @param  Collection<int, object{date: mixed, points: mixed}>  $dailyCollection
     * @return Collection<int, array{label: string, points: int}>
     */
    private function trendSeries($dailyCollection, Carbon $start, Carbon $end)
    {
        $dailyPoints = $dailyCollection->mapWithKeys(
            fn($row) => [Carbon::parse($row->date)->toDateString() => (int) $row->points],
        );

        $rangeInDays = $start
            ->copy()
            ->startOfDay()
            ->diffInDays($end->copy()->startOfDay());
        $groupMonthly = $rangeInDays > 62;

        if ($groupMonthly) {
            $monthlyPoints = [];
            foreach ($dailyPoints as $date => $points) {
                $key = Carbon::parse($date)->format('Y-m');
                $monthlyPoints[$key] = ($monthlyPoints[$key] ?? 0) + $points;
            }

            $series = collect();
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();
            while ($cursor->lte($last)) {
                $key = $cursor->format('Y-m');
                $series->push([
                    'label' => $cursor->format('M Y'),
                    'points' => (int) ($monthlyPoints[$key] ?? 0),
                ]);
                $cursor->addMonth();
            }

            return $series->values();
        }

        if ($rangeInDays > 14) {
            $weeklyPoints = [];
            foreach ($dailyPoints as $date => $points) {
                $week = Carbon::parse($date)->startOfWeek()->toDateString();
                $weeklyPoints[$week] = ($weeklyPoints[$week] ?? 0) + $points;
            }

            $series = collect();
            $cursor = $start->copy()->startOfWeek();
            $last = $end->copy()->startOfWeek();
            while ($cursor->lte($last)) {
                $weekStart = $cursor->copy();
                $weekEnd = $cursor->copy()->endOfWeek();
                $labelStart = $weekStart->lt($start) ? $start->copy() : $weekStart;
                $labelEnd = $weekEnd->gt($end) ? $end->copy() : $weekEnd;
                $series->push([
                    'label' => $labelStart->format('M j') . '-' . $labelEnd->format('M j'),
                    'points' => (int) ($weeklyPoints[$weekStart->toDateString()] ?? 0),
                ]);
                $cursor->addWeek();
            }

            return $series->values();
        }

        return collect(CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()))
            ->map(function (DateTimeInterface $day) use ($dailyPoints) {
                $date = Carbon::parse($day)->toDateString();

                return [
                    'label' => Carbon::parse($day)->format('M j'),
                    'points' => (int) ($dailyPoints[$date] ?? 0),
                ];
            })
            ->values();
    }
}
