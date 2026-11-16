<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangePasswordRequest;
use App\Services\AdminNotification\AdminNotificationReadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordRequestController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->has('status')
            ? ($request->string('status')->toString() === 'all'
                ? ''
                : $request->string('status')->toString())
            : RequestStatus::UnderReview->value;
        $statuses = [
            RequestStatus::UnderReview->value,
            RequestStatus::Approved->value,
            RequestStatus::Cancelled->value,
        ];
        $openRequestId = $request->integer('open_request');

        $requests = ChangePasswordRequest::query()
            ->with(['user:id,name,phone'])
            ->whereIn('status', $statuses)
            ->when($openRequestId < 1 && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('ticket_number', '%' . $search . '%')
                        ->orWhereLike('contact_name', '%' . $search . '%')
                        ->orWhereLike('contact_phone', '%' . $search . '%')
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query
                                ->whereLike('name', '%' . $search . '%')
                                ->orWhereLike('phone', '%' . $search . '%');
                        })
                        ->orWhereLike('broadband_account_number', '%' . $search . '%');
                });
            })
            ->when(
                $openRequestId < 1 && $status !== '' && in_array($status, $statuses, true),
                fn($query) => $query->where('status', $status),
            )
            ->when($openRequestId > 0, fn($query) => $query->whereKey($openRequestId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $unreadRequestIds = $readStatus->unreadReferenceIds(
            (int) $request->user()->getAuthIdentifier(),
            'change_password_request',
            $requests->getCollection()->pluck('id')->all(),
        );
        $requests->through(
            fn(ChangePasswordRequest $request): array => [
                ...$request->toArray(),
                'notification_unread' => in_array($request->id, $unreadRequestIds, true),
            ],
        );

        return Inertia::render('ServiceRequests/changePassword/Index', [
            'requests' => $requests,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => $statuses,
            'stats' => $this->stats($status),
        ]);
    }

    public function updateStatus(Request $request, ChangePasswordRequest $changePasswordRequest): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', new Enum(RequestStatus::class)]]);

        activity(ActivityLogName::ChangePasswordRequest->value)
            ->causedBy($request->user())
            ->performedOn($changePasswordRequest)
            ->event(ActivityLogEvent::StatusUpdated->value)
            ->log($validated['status']);

        $changePasswordRequest->update([
            'status' => $validated['status'],
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'change_password.status_updated');
    }

    private function stats(string $status): array
    {
        $requests = ChangePasswordRequest::query()
            ->whereIn('status', [
                RequestStatus::UnderReview,
                RequestStatus::Approved,
                RequestStatus::Cancelled,
            ])
            ->when(
                $status !== '' &&
                    in_array(
                        $status,
                        [
                            RequestStatus::UnderReview->value,
                            RequestStatus::Approved->value,
                            RequestStatus::Cancelled->value,
                        ],
                        true,
                    ),
                fn($query) => $query->where('status', $status),
            );

        return [
            'total_requests' => (clone $requests)->count(),
            'under_reviews_requests' => (clone $requests)
                ->where('status', RequestStatus::UnderReview)
                ->count(),
            'approved_requests' => (clone $requests)->where('status', RequestStatus::Approved)->count(),
            'cancelled_requests' => (clone $requests)->where('status', RequestStatus::Cancelled)->count(),
        ];
    }
}
