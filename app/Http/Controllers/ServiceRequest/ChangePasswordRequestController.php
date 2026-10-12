<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordRequestController extends Controller
{
    public function index(Request $request): Response
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
            ->when($openRequestId < 1 && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('contact_name', '%' . $search . '%')
                        ->orWhereLike('contact_phone', '%' . $search . '%')
                        ->orWhereLike('new_wifi_name', '%' . $search . '%')
                        ->orWhereHas('user', fn($query) => $query->whereLike('name', '%' . $search . '%'))
                        ->orWhereLike('broadband_account_number', '%' . $search . '%');
                });
            })
            ->whereIn('status', $statuses)
            ->when(
                $openRequestId < 1 && $status !== '' && in_array($status, $statuses, true),
                fn($query) => $query->where('status', $status),
            )
            ->when($openRequestId > 0, fn($query) => $query->whereKey($openRequestId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ServiceRequests/changePassword/Index', [
            'requests' => $requests,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => $statuses,
            'stats' => $this->stats(),
        ]);
    }

    public function updateStatus(Request $request, ChangePasswordRequest $changePasswordRequest): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', new Enum(RequestStatus::class)]]);

        activity('change_password_request')
            ->causedBy($request->user())
            ->performedOn($changePasswordRequest)
            ->event('status_updated')
            ->log('change_password_status_updated');

        $changePasswordRequest->update([
            'status' => $validated['status'],
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'change_password.status_updated');
    }

    private function stats(): array
    {
        return [
            'total_requests' => ChangePasswordRequest::query()->count(),
            'under_reviews_requests' => ChangePasswordRequest::query()
                ->where('status', RequestStatus::UnderReview)
                ->count(),
            'approved_requests' => ChangePasswordRequest::query()
                ->where('status', RequestStatus::Approved)
                ->count(),
            'cancelled_requests' => ChangePasswordRequest::query()
                ->where('status', RequestStatus::Cancelled)
                ->count(),
        ];
    }
}
