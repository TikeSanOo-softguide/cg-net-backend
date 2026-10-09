<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Services\Notification\DueFtthBillNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class BillDueAlertController extends Controller
{
    public function index(Request $request, DueFtthBillNotificationService $service): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $search = trim($filters['search'] ?? '');
        $dueDate = $filters['due_date'] ?? CarbonImmutable::today()->addDays(7)->toDateString();
        $alerts = collect($service->dueBillAlerts(dueDate: $dueDate))
            ->when($search !== '', fn ($alerts) => $alerts->filter(
                fn (array $alert): bool => str_contains(mb_strtolower($alert['customer_name']), mb_strtolower($search))
                    || str_contains(mb_strtolower($alert['account_number']), mb_strtolower($search)),
            ))
            ->sortBy(fn (array $alert): int => match ($alert['state']) {
                'not_recorded' => 0,
                'unsent' => 1,
                'sent' => 2,
            })
            ->values();
        $perPage = 15;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginatedAlerts = new LengthAwarePaginator(
            $alerts->slice(($currentPage - 1) * $perPage, $perPage)->values(),
            $alerts->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Notification/BillDueAlerts/Index', [
            'alerts' => $paginatedAlerts,
            'filters' => ['search' => $search, 'due_date' => $dueDate],
        ]);
    }

    public function send(Request $request, DueFtthBillNotificationService $service): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'account_number' => ['required', 'string', 'max:191'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'action_id' => ['required', 'string', 'max:191'],
        ]);

        $dispatched = $service->dispatchBillToUser(
            userId: (int) $data['user_id'],
            accountNumber: $data['account_number'],
            dueDate: $data['due_date'],
            actionId: $data['action_id'],
        );

        abort_unless(
            $dispatched,
            422,
            'This bill is no longer due, the account is no longer active, or the alert was already sent.',
        );

        return response()->json([
            'message' => __('notification.bill_due_alerts.queued'),
        ], 202);
    }
}
