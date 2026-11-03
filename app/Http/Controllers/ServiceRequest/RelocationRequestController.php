<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\RelocationRequest;
use App\Services\AdminNotification\AdminNotificationReadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class RelocationRequestController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->has('status')
            ? ($request->string('status')->toString() === 'all'
                ? ''
                : $request->string('status')->toString())
            : RequestStatus::UnderReview->value;
        $openRequestId = $request->integer('open_request');

        $relocationRequests = RelocationRequest::query()
            ->with(['user:id,name,phone', 'admin:id,username'])
            ->when($openRequestId < 1 && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereHas('user', fn($user) => $user->whereLike('name', '%' . $search . '%'))
                        ->orWhereLike('broadband_account_number', '%' . $search . '%');
                });
            })
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
            'relocation_request',
            $relocationRequests->getCollection()->pluck('id')->all(),
        );
        $relocationRequests->through(fn (RelocationRequest $relocationRequest): array => [
            ...$relocationRequest->toArray(),
            'notification_unread' => in_array($relocationRequest->id, $unreadRequestIds, true),
        ]);

        return Inertia::render('ServiceRequests/RelocationRequest/Index', [
            'requests' => $relocationRequests,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => array_column(RequestStatus::cases(), 'value'),
            'stats' => $this->stats(),
        ]);
    }

    public function updateStatus(Request $request, RelocationRequest $relocationRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(RequestStatus::class)],
        ]);

        activity('relocation_request')
            ->causedBy($request->user())
            ->performedOn($relocationRequest)
            ->event('status_updated')
            ->log($validated['status']);

        $relocationRequest->update([
            'status' => $validated['status'],
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'requests.status_updated');
    }

    private function stats(): array
    {
        return [
            'total_requests' => RelocationRequest::query()->count(),
            'under_reviews_requests' => RelocationRequest::query()
                ->where('status', RequestStatus::UnderReview)
                ->count(),
            'approved_requests' => RelocationRequest::query()->where('status', RequestStatus::Approved)->count(),
            'cancelled_requests' => RelocationRequest::query()->where('status', RequestStatus::Cancelled)->count(),
        ];
    }
}
