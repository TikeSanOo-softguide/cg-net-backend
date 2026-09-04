<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\ChangePlanStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangePlanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class ChangePlanRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->has('status')
            ? $request->string('status')->toString()
            : ChangePlanStatus::UnderReview->value;

        $changePlanRequests = ChangePlanRequest::query()
            ->with([
                'user:id,name,phone',
                'broadbandAccount:id,account_number',
                'currentPackage' => function ($query) {
                    $query->select('id', 'price', 'network_id', 'speed_id', 'term_id')
                        ->with(['network:id,name_en,name_zh,name_my', 'speed:id,mbps', 'term:id,months']);
                },
                'newPackage' => function ($query) {
                    $query->select('id', 'price', 'network_id', 'speed_id', 'term_id')
                        ->with(['network:id,name_en,name_zh,name_my', 'speed:id,mbps', 'term:id,months']);
                },
                'admin:id,username',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereHas('user', fn($query) => $query->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('broadbandAccount', fn($query) => $query->where('account_number', 'like', '%' . $search . '%'));
                });
            })
            ->when(
                $status !== '' && in_array($status, array_column(ChangePlanStatus::cases(), 'value'), true),
                fn($query) => $query->where('status', $status),
            )
            ->orderBy('preferred_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('ServiceRequests/changePlan/Index', [
            'requests' => $changePlanRequests,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => array_column(ChangePlanStatus::cases(), 'value'),
        ]);
    }

    public function updateStatus(Request $request, ChangePlanRequest $changePlanRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(ChangePlanStatus::class)],
        ]);

        $changePlanRequest->update([
            'status' => $validated['status'],
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'change_plan.status_updated');
    }
}
