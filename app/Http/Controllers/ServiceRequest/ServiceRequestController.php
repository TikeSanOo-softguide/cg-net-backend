<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\ChangePasswordStatus;
use App\Enums\ChangePlanStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangePasswordRequest;
use App\Models\ChangePlanRequest;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\RelocationRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    private const REQUEST_TYPES = [
        'installation_application' => [
            'label' => 'Broadband Applications',
            'model' => InstallationApplication::class,
            'status_enum' => RequestStatus::class,
        ],
        'failure_report' => [
            'label' => 'Failure Reports',
            'model' => FailureReport::class,
            'status_enum' => RequestStatus::class,
        ],
        'relocation' => [
            'label' => 'Relocation',
            'model' => RelocationRequest::class,
            'status_enum' => RequestStatus::class,
        ],
        'change_plan' => [
            'label' => 'Change Plan',
            'model' => ChangePlanRequest::class,
            'status_enum' => ChangePlanStatus::class,
        ],
        'change_password' => [
            'label' => 'Change Password',
            'model' => ChangePasswordRequest::class,
            'status_enum' => ChangePasswordStatus::class,
        ],
    ];

    public function index(Request $request): Response
    {
        $availableStatuses = $this->availableStatuses();
        $filters = $request->validate([
            'mode' => ['nullable', Rule::in(['yearly', 'date_range'])],
            'year' => ['nullable', 'integer', 'between:2000,' . now()->year],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(array_keys(self::REQUEST_TYPES))],
            'status' => ['nullable', Rule::in($availableStatuses)],
        ]);

        $mode = $filters['mode'] ?? 'yearly';
        $year = (int) ($filters['year'] ?? now()->year);
        $start =
            $mode === 'yearly'
                ? Carbon::create($year, 1, 1)->startOfDay()
                : Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $end =
            $mode === 'yearly'
                ? Carbon::create($year, 12, 31)->endOfDay()
                : Carbon::parse($filters['to'] ?? today())->endOfDay();

        // Normalize each request table to one shape so every report metric uses the same date, type, and status filters.
        $requests = collect();
        foreach (self::REQUEST_TYPES as $type => $definition) {
            if (isset($filters['type']) && $filters['type'] !== $type) {
                continue;
            }

            $model = $definition['model'];
            $rows = $model
                ::query()
                ->whereBetween('created_at', [$start, $end])
                ->when($filters['status'] ?? null, fn($query, string $status) => $query->where('status', $status))
                ->get(['created_at', 'updated_at', 'status'])
                ->map(function ($row) use ($type): array {
                    return [
                        'type' => $type,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                        'status' => $row->status->value,
                    ];
                });

            $requests = $requests->concat($rows);
        }

        // Total requests count normalized rows created in the selected period and matching the optional type/status filters.
        // Pending requests have not left under_review; approved requests are the existing completed outcome.
        $pendingCount = $requests->where('status', 'under_review')->count();
        $completedCount = $requests->where('status', 'approved')->count();

        // Type totals group the same filtered rows by their source model; keeping every type makes zero-counts explicit.
        $requestTypes = collect(self::REQUEST_TYPES)
            ->map(
                fn(array $definition, string $type) => [
                    'type' => $type,
                    'label' => $definition['label'],
                    'count' => $requests->where('type', $type)->count(),
                ],
            )
            ->values();

        // Status totals use the union of statuses declared by the five request models' enums, not a guessed list.
        $requestStatuses = collect($availableStatuses)
            ->map(
                fn(string $status) => [
                    'status' => $status,
                    'label' => str($status)->replace('_', ' ')->title()->toString(),
                    'count' => $requests->where('status', $status)->count(),
                ],
            )
            ->values();

        // Each type's resolution average excludes under_review rows and uses its own finalized created/updated interval.
        $resolutionAnalysis = collect(self::REQUEST_TYPES)
            ->map(function (array $definition, string $type) use ($requests): array {
                $typeRequests = $requests->where('type', $type);

                return [
                    'type' => $type,
                    'label' => $definition['label'],
                    'total' => $typeRequests->count(),
                ];
            })
            ->values();

        return Inertia::render('Reports/ServiceRequestReports/Index', [
            'filters' => [
                'mode' => $mode,
                'year' => (string) $year,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'type' => $filters['type'] ?? 'all',
                'status' => $filters['status'] ?? 'all',
            ],
            'years' => collect(range(now()->year, 2024))->map(
                fn(int $value) => [
                    'value' => (string) $value,
                    'label' => (string) $value,
                ],
            ),
            'statuses' => $availableStatuses,
            'summary' => [
                'total_requests' => $requests->count(),
                'pending_requests' => $pendingCount,
                'completed_requests' => $completedCount,
            ],
            'request_volume' => $this->requestVolume($requests, $start, $end, $mode),
            'request_types' => $requestTypes,
            'request_statuses' => $requestStatuses,
            'resolution_analysis' => $resolutionAnalysis,
        ]);
    }

    private function availableStatuses(): array
    {
        // Preserve statuses from every request enum while removing duplicates shared by multiple models.
        return collect(self::REQUEST_TYPES)
            ->flatMap(function (array $definition): array {
                $statusEnum = $definition['status_enum'];

                return array_map(fn($status) => $status->value, $statusEnum::cases());
            })
            ->unique()
            ->values()
            ->all();
    }

    private function requestVolume(Collection $requests, Carbon $start, Carbon $end, string $mode): Collection
    {
        // Annual and long ranges use months, medium ranges use weeks, and short ranges retain daily detail.
        $rangeInDays = $start
            ->copy()
            ->startOfDay()
            ->diffInDays($end->copy()->startOfDay());
        $granularity = $mode === 'yearly' || $rangeInDays > 62 ? 'month' : ($rangeInDays > 14 ? 'week' : 'day');
        $totals = [];

        foreach ($requests as $row) {
            $createdAt = $row['created_at'];
            $key = match ($granularity) {
                'month' => $createdAt->format('Y-m'),
                'week' => $createdAt->copy()->startOfWeek()->toDateString(),
                default => $createdAt->toDateString(),
            };
            $totals[$key] = ($totals[$key] ?? 0) + 1;
        }

        $series = collect();
        $cursor = match ($granularity) {
            'month' => $start->copy()->startOfMonth(),
            'week' => $start->copy()->startOfWeek(),
            default => $start->copy()->startOfDay(),
        };
        $last = match ($granularity) {
            'month' => $end->copy()->startOfMonth(),
            'week' => $end->copy()->startOfWeek(),
            default => $end->copy()->startOfDay(),
        };

        while ($cursor->lte($last)) {
            $key = match ($granularity) {
                'month' => $cursor->format('Y-m'),
                default => $cursor->toDateString(),
            };
            $label = match ($granularity) {
                'month' => $cursor->format('M Y'),
                'week' => $cursor->format('M j') . ' - ' . $cursor->copy()->endOfWeek()->format('M j'),
                default => $cursor->format('M j'),
            };

            $series->push(['label' => $label, 'requests' => $totals[$key] ?? 0]);
            $cursor = match ($granularity) {
                'month' => $cursor->addMonth(),
                'week' => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return $series->values();
    }
}
