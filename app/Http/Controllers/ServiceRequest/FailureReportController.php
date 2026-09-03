<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\FailureType;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\FailureReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FailureReportController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['contact_name', 'contact_phone', 'failure_type', 'status', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $reports = FailureReport::query()
            ->with([
                'user:id,name,phone',
                'broadbandAccount:id,account_number,customer_name',
                'photos:id,failure_report_id,image_url',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('contact_name', 'like', '%'.$search.'%')
                        ->orWhere('contact_phone', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('broadbandAccount', function ($query) use ($search): void {
                            $query->where('account_number', 'like', '%'.$search.'%')
                                ->orWhere('customer_name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($status !== '' && in_array($status, array_column(ReviewStatus::cases(), 'value'), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($type !== '' && in_array($type, array_column(FailureType::cases(), 'value'), true), function ($query) use ($type): void {
                $query->where('failure_type', $type);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (FailureReport $report) => [
                'id' => $report->id,
                'customer_name' => $report->user?->name ?? '—',
                'customer_phone' => $report->user?->phone ?? '—',
                'account_number' => $report->broadbandAccount?->account_number ?? '—',
                'account_customer' => $report->broadbandAccount?->customer_name ?? '—',
                'failure_type' => $report->failure_type->value,
                'contact_name' => $report->contact_name,
                'contact_phone' => $report->contact_phone,
                'description' => $report->description,
                'status' => $report->status->value,
                'created_at' => $report->created_at?->toDateString(),
                'photos' => $report->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'image_url' => $photo->image_url,
                    'label' => $photo->label ?? null,
                ])->all(),
            ]);

        return Inertia::render('ServiceRequests/Failures/Index', [
            'reports' => $reports,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function updateStatus(Request $request, FailureReport $failureReport): RedirectResponse
    {
        $status = $request->validate([
            'status' => ['required', Rule::enum(ReviewStatus::class)],
        ])['status'];

        if ($failureReport->status !== $status) {
            $failureReport->update(['status' => $status]);
        }

        return back()->with('success', 'common.update');
    }

    public function destroy(FailureReport $failureReport): RedirectResponse
    {
        $failureReport->delete();

        return back()->with('success', 'common.delete');
    }
}
