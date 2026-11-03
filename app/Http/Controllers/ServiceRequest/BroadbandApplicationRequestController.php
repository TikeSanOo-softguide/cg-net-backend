<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\InstallationApplication;
use App\Services\AdminNotification\AdminNotificationReadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class BroadbandApplicationRequestController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $search = trim((string) $request->string('search'));
        $serviceType = $request->string('service_type')->toString();
        if (! in_array($serviceType, ['FTTH', 'CG'], true)) {
            $serviceType = '';
        }
        $status = $request->has('status')
            ? ($request->string('status')->toString() === 'all'
                ? ''
                : $request->string('status')->toString())
            : RequestStatus::UnderReview->value;
        $openRequestId = $request->integer('open_request');

        $broadbandApplication = InstallationApplication::query()
            ->with([
                'user:id,name,phone,broadband_account_number',
                'admin:id,username',
                'area:id,name_en,name_zh,name_my,region_id',
                'area.region:id,name_en,name_zh,name_my,state_id',
                'area.region.state:id,name_en,name_zh,name_my',
            ])
            ->when($openRequestId < 1 && $search !== '', function ($query) use ($search): void {
                $search = "%{$search}%";

                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('phone', $search)
                        ->orWhereLike('name', $search)
                        ->orWhereLike('ticket_number', $search)
                        ->orWhereHas(
                            'user',
                            fn ($q) => $q
                                ->whereLike('name', $search)
                                ->orWhereLike('phone', $search)
                                ->orWhereLike('broadband_account_number', $search),
                        );
                });
            })
            ->when(
                $openRequestId < 1 && $serviceType !== '',
                fn ($query) => $query->where('service_type', $serviceType),
            )
            ->when(
                $openRequestId < 1 &&
                    $status !== '' &&
                    in_array($status, array_column(RequestStatus::cases(), 'value'), true),
                fn($query) => $query->where('status', $status),
            )
            ->when($openRequestId > 0, fn($query) => $query->whereKey($openRequestId))
            ->orderBy('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $unreadRequestIds = $readStatus->unreadReferenceIds(
            (int) $request->user()->getAuthIdentifier(),
            'installation_application',
            $broadbandApplication->getCollection()->pluck('id')->all(),
        );
        $broadbandApplication->through(fn (InstallationApplication $application): array => [
            ...$application->toArray(),
            'notification_unread' => in_array($application->id, $unreadRequestIds, true),
        ]);

        return Inertia::render('ServiceRequests/BroadbandApplication/Index', [
            'requests' => $broadbandApplication,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'service_type' => $serviceType,
            ],
            'statuses' => array_column(RequestStatus::cases(), 'value'),
            'stats' => $this->stats(),
        ]);
    }

    public function updateStatus(Request $request, InstallationApplication $installationApplication): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(RequestStatus::class)],
        ]);

        activity(ActivityLogName::InstallationApplication->value)
            ->causedBy($request->user())
            ->performedOn($installationApplication)
            ->event(ActivityLogEvent::StatusUpdated->value)
            ->log($validated['status']);

        $installationApplication->update([
            'status' => $validated['status'],
            'admin_id' => $request->user()->id,
        ]);

        return to_route('service-requests.installations.index', [
            'status' => $validated['status'],
        ])->with('success', 'requests.status_updated');
    }

    private function stats(): array
    {
        return [
            'total_requests' => InstallationApplication::query()->count(),
            'under_reviews_requests' => InstallationApplication::query()
                ->where('status', RequestStatus::UnderReview)
                ->count(),
            'approved_requests' => InstallationApplication::query()->where('status', RequestStatus::Approved)->count(),
            'cancelled_requests' => InstallationApplication::query()
                ->where('status', RequestStatus::Cancelled)
                ->count(),
        ];
    }
}
