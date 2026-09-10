<?php

namespace App\Http\Controllers\ActivityLog;

use App\Exports\ActivityLogExport;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->validateDateRange($request);
        $filters = $this->filters($request);
        $logs = $this->query($filters)
            ->paginate(15)
            ->withQueryString()
            ->through(fn(Activity $activity) => $this->payload($activity));

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'filterOptions' => [
                'event' => $this->distinctValues('event'),
                'log' => $this->distinctValues('log_name'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $this->validateDateRange($request);
        $filters = $this->filters($request);

        return Excel::download(
            new ActivityLogExport($this->query($filters)),
            'activity-logs-' . now()->format('Ymd-His') . '.xlsx',
        );
    }

    /** @return array{username: string, event: string, log: string, from: string, to: string, sort: string, direction: string} */
    private function filters(Request $request): array
    {
        $from = $this->dateFilter($request->string('from')->toString()) ?: today()->subDays(6)->toDateString();
        $to = $this->dateFilter($request->string('to')->toString()) ?: today()->toDateString();
        $sort = $request->string('sort')->toString();

        return [
            'username' => trim($request->string('username')->toString()),
            'event' => trim($request->string('event')->toString()),
            'log' => trim($request->string('log')->toString()),
            'from' => $from,
            'to' => $to,
            'sort' => in_array($sort, ['created_at', 'username', 'event', 'log_name'], true) ? $sort : 'created_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function dateFilter(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function validateDateRange(Request $request): void
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'event' => ['nullable', 'string'],
            'log' => ['nullable', 'string'],
        ]);
    }

    /** @param array{username: string, event: string, log: string, from: string, to: string, sort: string, direction: string} $filters */
    private function query(array $filters)
    {
        return Activity::query()
            ->with('causer')
            ->when($filters['username'] !== '', function ($query) use ($filters): void {
                $query
                    ->where('causer_type', (new Admin())->getMorphClass())
                    ->whereHas(
                        'causer',
                        fn($causer) => $causer->where('username', 'like', '%' . $filters['username'] . '%'),
                    );
            })
            ->when($filters['event'] !== '', fn($query) => $query->where('event', $filters['event']))
            ->when($filters['log'] !== '', fn($query) => $query->where('log_name', $filters['log']))
            ->when($filters['from'] !== '', fn($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(
                $filters['sort'] === 'username',
                fn($query) => $query->orderBy(
                    Activity::query()
                        ->select('username')
                        ->from('admins')
                        ->whereColumn('admins.id', 'activity_log.causer_id')
                        ->where('activity_log.causer_type', (new Admin())->getMorphClass())
                        ->limit(1),
                    $filters['direction'],
                ),
            )
            ->when(
                $filters['sort'] !== 'username',
                fn($query) => $query->orderBy($filters['sort'], $filters['direction']),
            )
            ->orderBy('id', 'desc');
    }

    private function distinctValues(string $column): array
    {
        return Activity::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter(fn($value) => $value !== null && $value !== '')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function payload(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'username' => $activity->causer?->username ?? 'System',
            'description' => $activity->description,
            'event' => $activity->event,
            'log_name' => $activity->log_name,
            'subject' => $this->subject($activity),
            'changes' => $this->changes($activity),
            'created_at' => $activity->created_at,
        ];
    }

    /** @return array<int, array{field: string, old: mixed, new: mixed}> */
    private function changes(Activity $activity): array
    {
        $changes = $activity->changes();
        $attributes = collect($changes->get('attributes', []));
        $old = collect($changes->get('old', []));

        return $attributes
            ->keys()
            ->merge($old->keys())
            ->unique()
            ->map(
                fn(string $field): array => [
                    'field' => $field,
                    'old' => $old->get($field),
                    'new' => $attributes->get($field),
                ],
            )
            ->values()
            ->all();
    }

    private function subject(Activity $activity): string
    {
        if (!$activity->subject_type || !$activity->subject_id) {
            return '';
        }

        return class_basename($activity->subject_type) . ' # ' . $activity->subject_id;
    }
}
