<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Services\Notification\DueInternetBillNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BillDueAlertController extends Controller
{
    public function index(Request $request, DueInternetBillNotificationService $service): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
        ]);
        $search = trim($filters['search'] ?? '');
        $loadErrorKey = null;

        try {
            $alerts = collect($service->dueBillAlerts());
        } catch (RuntimeException $exception) {
            report($exception);
            $alerts = collect();
            $loadErrorKey = 'notification.bill_due_alerts.load_failed';
        }

        $alerts = $alerts
            ->when(
                $search !== '',
                fn($alerts) => $alerts->filter(
                    fn(array $alert): bool => str_contains(
                        mb_strtolower($alert['customer_name']),
                        mb_strtolower($search),
                    ) || str_contains(mb_strtolower($alert['account_number']), mb_strtolower($search)),
                ),
            )
            ->sortBy(
                fn(array $alert): int => match ($alert['state']) {
                    'not_recorded' => 0,
                    'unsent' => 1,
                    'sent' => 2,
                },
            )
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
            'errorKey' => $loadErrorKey,
            'filters' => ['search' => $search],
        ]);
    }

    public function send(Request $request, DueInternetBillNotificationService $service): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'account_number' => ['required', 'string', 'max:191'],
            'endtime' => ['required', 'date_format:Y-m-d H:i'],
            'action_id' => ['required', 'string', 'max:191'],
        ]);

        $dispatched = $service->dispatchBillToUser(
            userId: (int) $data['user_id'],
            accountNumber: $data['account_number'],
            endTime: $data['endtime'],
            actionId: $data['action_id'],
        );

        abort_unless(
            $dispatched,
            422,
            'This due account entry is no longer current, the account is inactive, or the alert was already sent.',
        );

        return response()->json(
            [
                'message' => __('notification.bill_due_alerts.queued'),
            ],
            202,
        );
    }
}
