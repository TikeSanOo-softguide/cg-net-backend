<?php

namespace App\Http\Controllers\ServiceRequest;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\InstallationApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class BroadbandApplicationRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->has('status')
            ? ($request->string('status')->toString() === 'all'
                ? ''
                : $request->string('status')->toString())
            : RequestStatus::UnderReview->value;

        $broadbandApplication = InstallationApplication::query()
            ->with([
                'user:id,name,phone',
                'admin:id,username',
                'area:id,name_en,name_zh,name_my,region_id',
                'area.region:id,name_en,name_zh,name_my,state_id',
                'area.region.state:id,name_en,name_zh,name_my',
                'package:id,network_id,speed_id,term_id',
                'package.network:id,name_en,name_zh,name_my',
                'package.speed:id,mbps',
                'package.term:id,months',
                'photos:id,installation_application_id,image_url',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $search = "%{$search}%";

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('phone', 'like', $search)
                        ->orWhereHas('user', fn($q) => $q->where('name', 'like', $search))
                        ->orWhereHas('area', fn($q) => $q->where('name_en', 'like', $search)
                            ->orWhere('name_my', 'like', $search)->orWhere('name_zh', 'like', $search))
                        ->orWhereHas('area.region', fn($q) => $q->where('name_en', 'like', $search)->orWhere('name_my', 'like', $search)
                            ->orWhere('name_zh', 'like', $search));
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
            ->orderBy('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $broadbandApplication->getCollection()->transform(function ($application) {
            $application->photos->transform(function ($photo) {
                $photo->image_url = Storage::url($photo->image_url);

                return $photo;
            });
            return $application;
        });

        return Inertia::render('ServiceRequests/BroadbandApplication/Index', [
            'requests' => $broadbandApplication,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => array_column(RequestStatus::cases(), 'value'),
            'stats' => $this->stats(),
        ]);
    }

    public function updateStatus(
        Request $request,
        InstallationApplication $installationApplication
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', new Enum(RequestStatus::class)],
        ]);

        $installationApplication->update([
            'status' => $validated['status'],
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'requests.status_updated');
    }

    private function stats(): array
    {
        return [
            'total_requests' => InstallationApplication::query()->count(),
            'under_reviews_requests' => InstallationApplication::query()->where('status', RequestStatus::UnderReview)->count(),
            'approved_requests' => InstallationApplication::query()->where('status', RequestStatus::Approved)->count(),
            'cancelled_requests' => InstallationApplication::query()->where('status', RequestStatus::Cancelled)->count(),
        ];
    }
}
