<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangePlanRequest;
use App\Services\AdminNotification\AdminNotificationReadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class ChangePlanRequestController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $search = trim((string) $request->string('search'));
        $serviceType = $request->string('service_type')->toString();
        if (! in_array($serviceType, ['FTTH', 'CG'], true)) {
            $serviceType = '';
        }
        $status = $request->has('status')
            ? $request->string('status')->toString()
            : RequestStatus::UnderReview->value;
        $openRequestId = $request->integer('open_request');

        $changePlanRequests = ChangePlanRequest::query()
            ->with([
                'user:id,name,phone',
                'admin:id,username',
            ])
            ->when($openRequestId < 1 && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereHas('user', fn($user) => $user->whereLike('name', '%' . $search . '%'))
                        ->orWhereLike('broadband_account_number', '%' . $search . '%')
                        ->orWhereLike('ticket_number', '%' . $search . '%');
                });
            })
            ->when(
                $openRequestId < 1 && $serviceType !== '',
                fn ($query) => $query->where('broadband_account_number', 'like', $serviceType.'%'),
            )
            ->when(
                $openRequestId < 1 &&
                    $status !== '' &&
                    in_array($status, array_column(RequestStatus::cases(), 'value'), true),
                fn($query) => $query->where('status', $status),
            )
            ->when($openRequestId > 0, fn($query) => $query->whereKey($openRequestId))
            ->orderBy('preferred_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $unreadRequestIds = $readStatus->unreadReferenceIds(
            (int) $request->user()->getAuthIdentifier(),
            'change_plan_request',
            $changePlanRequests->getCollection()->pluck('id')->all(),
        );
        $changePlanRequests->through(fn (ChangePlanRequest $changePlanRequest): array => [
            ...$changePlanRequest->toArray(),
            'notification_unread' => in_array($changePlanRequest->id, $unreadRequestIds, true),
        ]);

        return Inertia::render('ServiceRequests/changePlan/Index', [
            'requests' => $changePlanRequests,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'service_type' => $serviceType,
            ],
            'statuses' => array_column(RequestStatus::cases(), 'value'),
            'stats' => $this->stats(),
        ]);
    }

    public function updateStatus(Request $request, ChangePlanRequest $changePlanRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(RequestStatus::class)],
        ]);

        activity('change_plan_request')
            ->causedBy($request->user())
            ->performedOn($changePlanRequest)
            ->event('status_updated')
            ->log($validated['status']);

        $changePlanRequest->update([
            'status' => $validated['status'],
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'change_plan.status_updated');
    }

    /**
     * @return array{total_requests: int, under_reviews_requests: int, approved_requests: int}
     */
    private function stats(): array
    {
        return [
            'total_requests' => ChangePlanRequest::query()->count(),
            'under_reviews_requests' => ChangePlanRequest::query()
                ->where('status', RequestStatus::UnderReview)
                ->count(),
            'approved_requests' => ChangePlanRequest::query()->where('status', RequestStatus::Approved)->count(),
            'cancelled_requests' => ChangePlanRequest::query()->where('status', RequestStatus::Cancelled)->count(),
        ];
    }
}
