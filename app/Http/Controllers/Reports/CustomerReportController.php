<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'mode' => ['nullable', Rule::in(['yearly', 'date_range'])],
            'year' => ['nullable', 'integer', 'between:2000,' . now()->year],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $mode = $filters['mode'] ?? (isset($filters['from']) || isset($filters['to']) ? 'date_range' : 'yearly');
        $year = (int) ($filters['year'] ?? now()->year);
        [$fromDate, $toDate] = $this->resolveDateRange($filters, $mode, $year);

        $filters = [
            'mode' => $mode,
            'year' => (string) $year,
            'from' => $fromDate,
            'to' => $toDate,
        ];

        $dateScopedUsers = $this->dateScopedUsers($filters);
        $statusCounts = (clone $dateScopedUsers)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $connectedUsers = (clone $dateScopedUsers)->whereNotNull('broadband_account_number')->count();
        $totalWalletPoints = (int) Wallet::query()
            ->whereIn('user_id', (clone $dateScopedUsers)->select('users.id'))
            ->sum('balance');

        return Inertia::render('Reports/CustomerReports/Index', [
            'filters' => $filters,
            'years' => collect(range(now()->year, 2024))->map(
                fn(int $value) => ['value' => (string) $value, 'label' => (string) $value],
            ),
            'summary' => [
                'total_users' => (int) $statusCounts->sum(),
                'active_users' => (int) ($statusCounts[UserStatus::Active->value] ?? 0),
                'suspended_users' => (int) ($statusCounts[UserStatus::Suspended->value] ?? 0),
                'connected_users' => $connectedUsers,
                'not_connected_users' => (int) $statusCounts->sum() - $connectedUsers,
                'total_wallet_points' => $totalWalletPoints,
            ],
        ]);
    }

    /** @param array{from: string, to: string, status: string} $filters */
    private function dateScopedUsers(array $filters): Builder
    {
        return User::query()
            ->when(
                $filters['from'] !== '',
                fn(Builder $query) => $query->whereDate('created_at', '>=', $filters['from']),
            )
            ->when($filters['to'] !== '', fn(Builder $query) => $query->whereDate('created_at', '<=', $filters['to']));
    }

    /** @return array{0: string, 1: string} */
    private function resolveDateRange(array $filters, string $mode, int $year): array
    {
        if ($mode === 'yearly') {
            return [Carbon::create($year, 1, 1)->toDateString(), Carbon::create($year, 12, 31)->toDateString()];
        }

        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();

        return [$from, $to];
    }
}
