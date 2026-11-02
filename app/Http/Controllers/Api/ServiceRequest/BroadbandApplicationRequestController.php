<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\MessageCode;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateBroadbandApplicationRequest;
use App\Http\Requests\ServiceRequest\UpdateBroadbandApplicationRequest;
use App\Http\Resources\BroadbandApplication\BroadbandApplicationResource;
use App\Events\ServiceRequestSubmitted;
use App\Models\InstallationApplication;
use App\Services\Package\CgNetProductCatalog;
use App\Services\Telegram\TelegramService;
use App\Support\ApiMessage;
use App\Support\GeneratesTicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BroadbandApplicationRequestController extends Controller
{
    public function __construct(
        private TelegramService $telegram,
        private CgNetProductCatalog $catalog,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
        ]);

        try {
            $products = $this->catalog->productsForServiceType($validated['service_type']);
        } catch (RuntimeException $exception) {
            Log::warning('CG-Net installation catalog unavailable.', [
                'service_type' => $validated['service_type'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json(
                ApiMessage::attach(MessageCode::PkgCatalogUnavailable),
                503,
            );
        }

        return response()->json(['data' => $products]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $applications = InstallationApplication::query()
            ->with(['area.region.state'])
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
                'ticket_number' => GeneratesTicketNumber::next(
                    InstallationApplication::class,
                    'ticket_number',
                    'INT'.now()->format('md'),
                ),
                'user_id' => $request->user()->id,
                'status' => RequestStatus::UnderReview->value,
            ]);
            ServiceRequestSubmitted::dispatch('installation_application', $application->id);

            $this->logActivity($request, 'create_broadband_application', [
                'broadband_application_id' => $application->id,
                'status' => $application->status->value,
            ]);

            return $application;
        });

        $application->load([
            'user:id,name,phone',
            'admin:id,username',
            'area:id,name_en,name_zh,name_my,region_id',
            'area.region:id,name_en,name_zh,name_my,state_id',
            'area.region.state:id,name_en,name_zh,name_my',
        ]);

        $detail = $application->package_detail ?? [];
        $message =
            "📡 Broadband Installation\n\n" .
            "🗓️ Date: {$application->updated_at->format('d M Y, h:i A')}\n" .
            "👤 Customer: {$application->name}\n" .
            "📞 Phone: {$application->phone}\n" .
            "📍 Area: {$application->area->name_en}\n" .
            "🌐 Region: {$application->area->region->name_en}\n" .
            "📦 Package: ".($detail['product_name'] ?? '—')."\n" .
            "⚡ Speed: ".($detail['product_speed'] ?? '—')."\n" .
            "📅 Term: ".($detail['month_cycle'] ?? '—').' '.($detail['month_cycle_unit'] ?? '')."\n" .
            "📋 Status: {$application->status->value}";

        $this->telegram->broadbandApplicaiton($message);
        $application->load(['area.region.state']);

        return (new BroadbandApplicationResource($application))
            ->additional(ApiMessage::attach(MessageCode::ReqSubmitted));
    }

    public function update(
        UpdateBroadbandApplicationRequest $request,
        InstallationApplication $installationApplication,
    ): BroadbandApplicationResource {
        $this->ensureOwner($request, $installationApplication);

        return DB::transaction(function () use ($request, $installationApplication) {
            $validated = $request->validated();
            $installationApplication->update($validated);

            $this->logActivity($request, 'update_broadband_application', [
                'broadband_application_id' => $installationApplication->id,
            ]);

            return (new BroadbandApplicationResource(
                $installationApplication
                    ->refresh()
                    ->load(['area.region.state']),
            ))->additional(ApiMessage::attach(MessageCode::ReqUpdated));
        });
    }

    public function destroy(Request $request, InstallationApplication $installationApplication): Response
    {
        $this->ensureOwner($request, $installationApplication);
        $installationApplication->delete();

        $this->logActivity($request, 'delete_broadband_application', [
            'broadband_application_id' => $installationApplication->id,
        ]);

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

        $this->logActivity($request, 'cancel_broadband_application', [
            'broadband_application_id' => $installationApplication->id,
        ]);

        return (new BroadbandApplicationResource($installationApplication->refresh()))
            ->additional(ApiMessage::attach(MessageCode::ReqCancelled));
    }

    private function ensureOwner(Request $request, InstallationApplication $installationApplication): void
    {
        if ($installationApplication->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }

}
