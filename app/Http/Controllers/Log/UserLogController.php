<?php

namespace App\Http\Controllers\Log;

use App\Exports\UserLogExport;
use App\Http\Controllers\Controller;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class UserLogController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'event' => ['nullable', 'string'],
        ]);
        $filters = $this->filters($request);
        $logs = $this->query($filters)->paginate(15)->withQueryString()->through(
            fn(UserLog $log) => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'event' => $log->event,
                'user' => $log->user
                    ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'phone' => $log->user->phone,
                    ]
                    : null,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at,
            ],
        );

        return Inertia::render('Logs/UserLog/index', [
            'logs' => $logs,
            'filters' => $filters,
            'filterOptions' => [
                'event' => UserLog::query()
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
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'event' => ['nullable', 'string'],
        ]);
        $filters = $this->filters($request);

        return Excel::download(new UserLogExport($this->query($filters)), 'user-logs.xlsx');
    }

    /** @return array{event: string, from: string, to: string, sort: string, direction: string} */
    private function filters(Request $request): array
    {
        $sort = $request->string('sort')->toString();

        return [
            'event' => trim($request->string('event')->toString()),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
            'sort' => in_array($sort, ['created_at', 'event', 'ip_address'], true) ? $sort : 'created_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** @param array{event: string, from: string, to: string, sort: string, direction: string} $filters */
    private function query(array $filters)
    {
        return UserLog::query()
            ->with('user')
            ->when($filters['event'] !== '', fn($query) => $query->where('event', $filters['event']))
            ->when($filters['from'] !== '', fn($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id', 'desc');
    }
}
