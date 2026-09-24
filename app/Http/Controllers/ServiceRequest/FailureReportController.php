<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\FailureType;
use App\Enums\RequestStatus;
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
        $status = $request->has('status')
            ? ($request->string('status')->toString() === 'all'
                ? ''
                : $request->string('status')->toString())
            : RequestStatus::UnderReview->value;

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
                'admin:id,username',
                'user.customerPackages:id,user_id,broadband_account_id,package_id,start_date,expiry_date',
                'user.customerPackages.package:id,network_id,speed_id,term_id,price',
                'user.customerPackages.package.network:id,name_en,name_zh,name_my',
                'user.customerPackages.package.speed:id,mbps',
                'user.customerPackages.package.term:id,months',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('contact_name', '%' . $search . '%')
                        ->orWhereLike('contact_phone', '%' . $search . '%')
                        ->orWhereLike('description', '%' . $search . '%')
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->whereLike('name', '%' . $search . '%')
                                ->orWhereLike('phone', '%' . $search . '%');
                        })
                        ->orWhereHas('broadbandAccount', function ($query) use ($search): void {
                            $query->whereLike('account_number', '%' . $search . '%')
                                ->orWhereLike('customer_name', '%' . $search . '%');
                        });
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
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn(FailureReport $report) => [
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
                'admin_name' =>  $report->admin?->username,
                'photos' => $report->photos->map(fn($photo) => [
                    'id' => $photo->id,
                    'image_url' => $photo->image_url,
                    'label' => $photo->label ?? null,
                ])->all(),
                'customer_packages' => $report->user?->customerPackages
                    ->map(fn($customerPackage) => [
                        'id' => $customerPackage->id,
                        'package_id' => $customerPackage->package_id,
                        'start_date' => $customerPackage->start_date,
                        'expiry_date' => $customerPackage->expiry_date,
                        'package' => $customerPackage->package ? [
                            'id' => $customerPackage->package->id,
                            'network' => $customerPackage->package->network ? [
                                'id' => $customerPackage->package->network->id,
                                'name_en' => $customerPackage->package->network->name_en,
                                'name_zh' => $customerPackage->package->network->name_zh,
                                'name_my' => $customerPackage->package->network->name_my,
                            ] : null,
                            'speed' => $customerPackage->package->speed ? [
                                'id' => $customerPackage->package->speed->id,
                                'mbps' => $customerPackage->package->speed->mbps,
                            ] : null,
                            'term' => $customerPackage->package->term ? [
                                'id' => $customerPackage->package->term->id,
                                'months' => $customerPackage->package->term->months,
                            ] : null,
                        ] : null,
                    ])
                    ->values()
                    ->all(),
            ]);

        return Inertia::render('ServiceRequests/Failures/Index', [
            'reports' => $reports,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => array_column(RequestStatus::cases(), 'value'),
            'stats' => $this->stats(),
        ]);
    }

    public function updateStatus(Request $request, FailureReport $failureReport): RedirectResponse
    {
        $status = $request->validate([
            'status' => ['required', Rule::enum(RequestStatus::class)],
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

    private function stats(): array
    {
        return [
            'total_requests' => FailureReport::query()->count(),
            'under_reviews_requests' => FailureReport::query()->where('status', RequestStatus::UnderReview)->count(),
            'approved_requests' => FailureReport::query()->where('status', RequestStatus::Approved)->count(),
            'cancelled_requests' => FailureReport::query()->where('status', RequestStatus::Cancelled)->count(),
        ];
    }
}
