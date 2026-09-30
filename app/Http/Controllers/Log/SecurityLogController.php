<?php

namespace App\Http\Controllers\Log;

use App\Exports\SecurityLogExport;
use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Inertia\Response;

class SecurityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $logs = $this->query($filters)
            ->paginate(15)
            ->withQueryString()
            ->through(
                fn(SecurityLog $log) => (new \App\Http\Resources\Log\SecurityLogResource($log))->resolve($request),
            );

        return Inertia::render('Logs/SecurityLog/index', [
            'logs' => $logs,
            'filters' => $filters,
            'filterOptions' => [
                'event' => SecurityLog::query()
                    ->whereNotNull('event')
                    ->where('event', '!=', '')
                    ->distinct()
                    ->orderBy('event')
                    ->pluck('event')
                    ->all(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(new SecurityLogExport($this->query($this->filters($request))), 'security-logs.xlsx');
    }

    /** @return array{event: string, from: string, to: string, sort: string, direction: string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'event' => ['nullable', 'string'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $sort = $request->string('sort')->toString();

        return [
            'event' => trim($validated['event'] ?? ''),
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
            'sort' => in_array($sort, ['created_at', 'event', 'ip_address'], true) ? $sort : 'created_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** @param array{event: string, from: string, to: string, sort: string, direction: string} $filters */
    private function query(array $filters): Builder
    {
        return SecurityLog::query()
            ->with('actor')
            ->when($filters['event'] !== '', fn($query) => $query->where('event', $filters['event']))
            ->when($filters['from'] !== '', fn($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id', 'desc');
    }
}
