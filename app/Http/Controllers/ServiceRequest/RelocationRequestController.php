<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\RelocationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class RelocationRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->has('status')
            ? ($request->string('status')->toString() === 'all'
                ? ''
                : $request->string('status')->toString())
            : RequestStatus::UnderReview->value;

        $relocationRequests = RelocationRequest::query()
            ->with([
                'user:id,name,phone',
                'broadbandAccount:id,account_number,customer_name',
                'admin:id,username',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereHas(
                            'user',
                            fn($query) => $query->where(
                                'name',
                                'like',
                                '%' . $search . '%'
                            )
                        )
                        ->orWhereHas(
                            'broadbandAccount',
                            fn($query) => $query->where(
                                'account_number',
                                'like',
                                '%' . $search . '%'
                            )
                        );
                });
            })
            ->when(
                $status !== ''
                    && in_array(
                        $status,
                        array_column(RequestStatus::cases(), 'value'),
                        true
                    ),
                fn($query) => $query->where('status', $status),
            )
            ->orderBy('preferred_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

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
            'under_reviews_requests' => RelocationRequest::query()->where('status', RequestStatus::UnderReview)->count(),
            'approved_requests' => RelocationRequest::query()->where('status', RequestStatus::Approved)->count(),
            'cancelled_requests' => RelocationRequest::query()->where('status', RequestStatus::Cancelled)->count(),
        ];
    }
}
