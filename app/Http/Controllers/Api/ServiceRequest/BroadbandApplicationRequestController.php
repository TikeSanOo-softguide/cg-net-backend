<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateBroadbandApplicationRequest;
use App\Http\Requests\ServiceRequest\UpdateBroadbandApplicationRequest;
use App\Http\Resources\BroadbandApplication\BroadbandApplicationResource;
use App\Models\InstallationApplication;
use App\Services\TelegramService;
use App\Support\StoresPublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BroadbandApplicationRequestController extends Controller
{
    public function __construct(private TelegramService $telegram) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $applications = InstallationApplication::query()
            ->with(['package.network', 'package.speed', 'package.term', 'area.region.state', 'photos'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return BroadbandApplicationResource::collection($applications);
    }

    public function show(
        Request $request,
        InstallationApplication $installationApplication,
    ): BroadbandApplicationResource {
        $this->ensureOwner($request, $installationApplication);
        return new BroadbandApplicationResource($installationApplication);
    }

    public function store(CreateBroadbandApplicationRequest $request): BroadbandApplicationResource
    {
        $application = DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $application = InstallationApplication::query()->create([
                ...$validated,
                'user_id' => $request->user()->id,
                'status' => RequestStatus::UnderReview->value,
            ]);

            foreach ($request->file('photos', []) as $photo) {
                $application->photos()->create([
                    'image_url' => StoresPublicImage::store($photo, 'service-request/broadband-applications'),
                ]);
            }

            return $application;
        });

        $application->load([
            'user:id,name,phone',
            'admin:id,username',
            'area:id,name_en,name_zh,name_my,region_id',
            'area.region:id,name_en,name_zh,name_my,state_id',
            'area.region.state:id,name_en,name_zh,name_my',
            'package:id,network_id,speed_id,term_id',
            'package.network:id,name_en,name_zh,name_my',
            'package.speed:id,mbps',
            'package.term:id,months',
        ]);

        $message =
            "📡 Broadband Installation\n\n" .
            "🗓️ Date: {$application->updated_at->format('d M Y, h:i A')}\n" .
            "👤 Customer: {$application->user->name}\n" .
            "📞 Phone: {$application->user->phone}\n" .
            "📍 Area: {$application->area->name_en}\n" .
            "🌐 Region: {$application->area->region->name_en}\n" .
            "📦 Package: {$application->package->network->name_en}\n" .
            "⚡ Speed: {$application->package->speed->mbps} Mbps\n" .
            "📅 Term: {$application->package->term->months} months\n" .
            "📋 Status: {$application->status->value}";

        $this->telegram->sendMessage($message);
        $application->load(['package.network', 'package.speed', 'package.term', 'area.region.state', 'photos']);

        return new BroadbandApplicationResource($application);
    }

    public function update(
        UpdateBroadbandApplicationRequest $request,
        InstallationApplication $installationApplication,
    ): BroadbandApplicationResource {
        $this->ensureOwner($request, $installationApplication);

        return DB::transaction(function () use ($request, $installationApplication) {
            $validated = $request->validated();
            $installationApplication->update($validated);

            if ($request->hasFile('photos')) {
                $photos = $request->file('photos');
                $existingPhotos = $installationApplication->photos()->get();

                foreach ($photos as $index => $photo) {
                    $existingPhoto = $existingPhotos->get($index);
                    $imageUrl = StoresPublicImage::store(
                        $photo,
                        'service-request/broadband-applications',
                        $existingPhoto?->image_url,
                    );

                    if ($existingPhoto) {
                        $existingPhoto->update(['image_url' => $imageUrl]);
                    } else {
                        $installationApplication->photos()->create(['image_url' => $imageUrl]);
                    }
                }
            }

            return new BroadbandApplicationResource(
                $installationApplication
                    ->refresh()
                    ->load(['package.network', 'package.speed', 'package.term', 'area.region.state', 'photos']),
            );
        });
    }

    public function destroy(Request $request, InstallationApplication $installationApplication): Response
    {
        $this->ensureOwner($request, $installationApplication);
        DB::transaction(function () use ($installationApplication) {
            foreach ($installationApplication->photos as $photo) {
                StoresPublicImage::delete($photo->image_url);
                $photo->delete();
            }
            $installationApplication->delete();
        });

        return response()->noContent();
    }

    public function cancel(
        Request $request,
        InstallationApplication $installationApplication,
    ): BroadbandApplicationResource {
        $this->ensureOwner($request, $installationApplication);
        $installationApplication->update([
            'status' => RequestStatus::Cancelled,
        ]);

        return new BroadbandApplicationResource($installationApplication->refresh());
    }

    private function ensureOwner(Request $request, InstallationApplication $installationApplication): void
    {
        if ($installationApplication->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }
}
