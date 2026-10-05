<?php

namespace App\Http\Controllers\AdminNotification;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\ChangePasswordRequest;
use App\Models\ChangePlanRequest;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\RelocationRequest;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationController extends Controller
{
    // When adding a request reference type, register its model and table here so admin
    // filtering and customer/status lookup can resolve the saved reference.
    /**
     * @var array<string, class-string>
     */
    private const REQUEST_MODELS = [
        'installation_application' => InstallationApplication::class,
        'change_password_request' => ChangePasswordRequest::class,
        'change_plan_request' => ChangePlanRequest::class,
        'failure_report' => FailureReport::class,
        'relocation_request' => RelocationRequest::class,
    ];

    /**
     * @var array<string, string>
     */
    private const CUSTOMER_TABLES = [
        'installation_application' => 'installation_applications',
        'change_password_request' => 'change_password_requests',
        'change_plan_request' => 'change_plan_requests',
        'failure_report' => 'failure_reports',
        'relocation_request' => 'relocation_requests',
    ];

    public function index(Request $request): JsonResponse|Response
    {
        if (! $request->expectsJson()) {
            return $this->page($request);
        }

        $limit = $request->integer('limit', 5);
        abort_if($limit < 1 || $limit > 20, 422, 'The notification limit must be between 1 and 20.');

        $notifications = AdminNotification::query()
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AdminNotification $notification): array => $notification->toDropdownArray())
            ->values();

        return response()->json([
            'data' => $notifications,
            'unread_count' => AdminNotification::query()->whereNull('read_at')->count(),
        ]);
    }

    private function page(Request $request): Response
    {
        // Categories are read from stored notifications, so new notification types appear automatically.
        $categories = AdminNotification::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in($categories)],
            'request_type' => ['nullable', Rule::in(array_keys(self::REQUEST_MODELS))],
            'status' => ['nullable', Rule::in(['read', 'unread'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $filters['search'] = trim($filters['search'] ?? '');

        $notifications = AdminNotification::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.$search.'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query
                        ->whereLike('title', $term)
                        ->orWhereLike('body', $term);

                    foreach (self::CUSTOMER_TABLES as $type => $table) {
                        $query->orWhere(function (Builder $query) use ($type, $table, $term): void {
                            $query
                                ->where('reference_type', $type)
                                ->whereExists(function ($subquery) use ($table, $term): void {
                                    $subquery
                                        ->selectRaw('1')
                                        ->from($table)
                                        ->join('users', 'users.id', '=', $table.'.user_id')
                                        ->whereColumn($table.'.id', 'admin_notifications.reference_id')
                                        ->whereNull($table.'.deleted_at')
                                        ->whereLike('users.name', $term);
                                });
                        });
                    }
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(
                $filters['request_type'] ?? null,
                fn (Builder $query, string $type) => $query->where('reference_type', $type),
            )
            ->when(
                ($filters['status'] ?? null) === 'read',
                fn (Builder $query) => $query->whereNotNull('read_at'),
            )
            ->when(
                ($filters['status'] ?? null) === 'unread',
                fn (Builder $query) => $query->whereNull('read_at'),
            )
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $requestData = $this->requestData($notifications->getCollection());
        $notifications->getCollection()->transform(function (AdminNotification $notification) use ($requestData): array {
            $record = $requestData[$notification->reference_type][$notification->reference_id] ?? null;
            $dropdown = $notification->toDropdownArray();

            return [
                ...$dropdown,
                'customer_name' => $record['customer_name'] ?? null,
                'request_status' => $record['request_status'] ?? null,
            ];
        });

        return Inertia::render('Notification/Notification/Index', [
            'notifications' => $notifications,
            'categories' => $categories,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'request_type' => $filters['request_type'] ?? '',
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }

    /**
     * @param  Collection<int, AdminNotification>  $notifications
     * @return array<string, array<int, array{customer_name: string|null, request_status: string|null}>>
     */
    private function requestData(Collection $notifications): array
    {
        $result = [];

        foreach (self::REQUEST_MODELS as $type => $model) {
            $ids = $notifications
                ->where('reference_type', $type)
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $records = $model::query()
                ->with('user:id,name')
                ->whereKey($ids)
                ->get();

            foreach ($records as $record) {
                $status = $record->status;
                $result[$type][$record->id] = [
                    'customer_name' => $record->user?->name,
                    'request_status' => $status instanceof BackedEnum ? $status->value : $status,
                ];
            }
        }

        return $result;
    }

    public function markRead(Request $request, AdminNotification $notification): JsonResponse|RedirectResponse
    {
        if ($notification->read_at === null) {
            $notification->update([
                'read_at' => now(),
                'is_read' => true,
            ]);
        }

        return $request->expectsJson()
            ? response()->json(['data' => $notification->refresh()->toDropdownArray()])
            : back();
    }
}
