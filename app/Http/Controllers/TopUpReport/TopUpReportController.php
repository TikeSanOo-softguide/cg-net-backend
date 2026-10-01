<?php

namespace App\Http\Controllers\TopUpReport;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\TopUpCard;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TopUpReportController extends Controller
{
    public function index(Request $request): Response
    {
        $amounts = TopUpCard::query()->select('amount')->distinct()->orderBy('amount')->pluck('amount');
        $filters = $request->validate([
            'mode' => ['nullable', Rule::in(['yearly', 'date_range'])],
            'year' => ['nullable', 'integer', 'between:2000,' . now()->year],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'office' => ['nullable', 'integer', 'exists:offices,id'],
            'amount' => ['nullable', 'integer', Rule::in($amounts->all())],
        ]);

        $mode = $filters['mode'] ?? (isset($filters['from']) || isset($filters['to']) ? 'date_range' : 'yearly');
        $year = (int) ($filters['year'] ?? now()->year);
        [$fromDate, $toDate] = $this->resolveDateRange($filters, $mode, $year);
        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();

        $cards = TopUpCard::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['office'] ?? null, fn($query, int $office) => $query->where('office_id', $office))
            ->when($filters['amount'] ?? null, fn($query, int $amount) => $query->where('amount', $amount));

        $totalCards = (clone $cards)->count();
        $usedCards = (clone $cards)->where('status', TopUpCardStatus::Used)->count();
        $availableCards = (clone $cards)
            ->whereIn('status', [TopUpCardStatus::Pending, TopUpCardStatus::Active])
            ->whereDate('expires_at', '>=', today())
            ->count();

        $dailyActivity = (clone $cards)
            ->selectRaw(
                'DATE(created_at) as date, COUNT(*) as generated, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as redeemed',
                [TopUpCardStatus::Used->value],
            )
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();
        $trend = $this->activitySeries($dailyActivity, $start, $end);

        $officeDistribution = (clone $cards)
            ->with('office:id,name,cd')
            ->selectRaw('office_id, COUNT(*) as cards')
            ->groupBy('office_id')
            ->get()
            ->map(function (TopUpCard $card) use ($totalCards): array {
                $office = $card->office;

                return [
                    'name' => $office?->name,
                    'code' => $office?->cd,
                    'cards' => (int) $card->cards,
                    'percentage' => $totalCards > 0 ? round(((int) $card->cards / $totalCards) * 100, 1) : 0,
                ];
            })
            ->values();

        return Inertia::render('Reports/Top-UpReports/Index', [
            'filters' => [
                'mode' => $mode,
                'year' => (string) $year,
                'from' => $fromDate,
                'to' => $toDate,
                'office' => isset($filters['office']) ? (string) $filters['office'] : 'all',
                'amount' => isset($filters['amount']) ? (string) $filters['amount'] : 'all',
            ],
            'years' => collect(range(now()->year, 2024))->map(
                fn(int $value) => ['value' => (string) $value, 'label' => (string) $value],
            ),
            'summary' => [
                'total_cards' => $totalCards,
                'used_cards' => $usedCards,
                'left_cards' => $availableCards,
            ],
            'trend' => $trend,
            'hasCards' => $totalCards > 0,
            'officeDistribution' => $officeDistribution,
            'offices' => Office::query()
                ->orderBy('cd')
                ->orderBy('name')
                ->get(['id', 'name', 'cd'])
                ->map(
                    fn(Office $office) => [
                        'value' => (string) $office->id,
                        'label' => $office->name,
                        'code' => $office->cd,
                    ],
                )
                ->values(),
            'amounts' => $amounts->map(fn($amount) => (string) $amount)->values(),
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

    /**
     * @param Collection<int, TopUpCard> $dailyActivity
     * @return Collection<int, array{label: string, generated: int, redeemed: int}>
     */
    private function activitySeries(Collection $dailyActivity, Carbon $start, Carbon $end): Collection
    {
        $daily = $dailyActivity->mapWithKeys(
            fn($row) => [
                Carbon::parse($row->date)->toDateString() => [
                    'generated' => (int) $row->generated,
                    'redeemed' => (int) $row->redeemed,
                ],
            ],
        );
        $rangeInDays = $start
            ->copy()
            ->startOfDay()
            ->diffInDays($end->copy()->startOfDay());

        if ($rangeInDays > 62) {
            $grouped = [];
            foreach ($daily as $date => $counts) {
                $key = Carbon::parse($date)->format('Y-m');
                foreach ($counts as $type => $count) {
                    $grouped[$key][$type] = ($grouped[$key][$type] ?? 0) + $count;
                }
            }

            $series = collect();
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();
            while ($cursor->lte($last)) {
                $key = $cursor->format('Y-m');
                $series->push([
                    'label' => $cursor->format('M Y'),
                    'generated' => (int) ($grouped[$key]['generated'] ?? 0),
                    'redeemed' => (int) ($grouped[$key]['redeemed'] ?? 0),
                ]);
                $cursor->addMonth();
            }

            return $series->values();
        }

        if ($rangeInDays > 14) {
            $grouped = [];
            foreach ($daily as $date => $counts) {
                $key = Carbon::parse($date)->startOfWeek()->toDateString();
                foreach ($counts as $type => $count) {
                    $grouped[$key][$type] = ($grouped[$key][$type] ?? 0) + $count;
                }
            }

            $series = collect();
            $cursor = $start->copy()->startOfWeek();
            $last = $end->copy()->startOfWeek();
            while ($cursor->lte($last)) {
                $weekEnd = $cursor->copy()->endOfWeek();
                $labelStart = $cursor->lt($start) ? $start : $cursor;
                $labelEnd = $weekEnd->gt($end) ? $end : $weekEnd;
                $key = $cursor->toDateString();
                $series->push([
                    'label' => $labelStart->format('M j') . '-' . $labelEnd->format('M j'),
                    'generated' => (int) ($grouped[$key]['generated'] ?? 0),
                    'redeemed' => (int) ($grouped[$key]['redeemed'] ?? 0),
                ]);
                $cursor->addWeek();
            }

            return $series->values();
        }

        return collect(CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()))
            ->map(function (DateTimeInterface $day) use ($daily): array {
                $date = Carbon::parse($day)->toDateString();
                $counts = $daily[$date] ?? [];

                return [
                    'label' => Carbon::parse($day)->format('M j'),
                    'generated' => (int) ($counts['generated'] ?? 0),
                    'redeemed' => (int) ($counts['redeemed'] ?? 0),
                ];
            })
            ->values();
    }
}
