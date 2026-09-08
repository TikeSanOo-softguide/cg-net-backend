<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\ChangePasswordStatus;
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
            : ChangePasswordStatus::UnderReview->value;
        $statuses = [
            ChangePasswordStatus::UnderReview->value,
            ChangePasswordStatus::Approved->value,
            ChangePasswordStatus::Cancelled->value,
        ];

        $requests = ChangePasswordRequest::query()
            ->with(['user:id,name,phone', 'broadbandAccount:id,account_number'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('contact_name', 'like', '%' . $search . '%')
                        ->orWhere('contact_phone', 'like', '%' . $search . '%')
                        ->orWhere('new_wifi_name', 'like', '%' . $search . '%')
                        ->orWhereHas(
                            'broadbandAccount',
                            fn($query) => $query->where('account_number', 'like', '%' . $search . '%'),
                        );
                });
            })
            ->whereIn('status', $statuses)
            ->when($status !== '' && in_array($status, $statuses, true), fn($query) => $query->where('status', $status))
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
        $validated = $request->validate(['status' => ['required', new Enum(ChangePasswordStatus::class)]]);

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
                ->where('status', ChangePasswordStatus::UnderReview)
                ->count(),
            'approved_requests' => ChangePasswordRequest::query()
                ->where('status', ChangePasswordStatus::Approved)
                ->count(),
            'cancelled_requests' => ChangePasswordRequest::query()
                ->where('status', ChangePasswordStatus::Cancelled)
                ->count(),
        ];
    }
}
