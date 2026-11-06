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
    public function __construct(private TelegramService $telegram, private CgNetProductCatalog $catalog) {}

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

            return response()->json(ApiMessage::attach(MessageCode::PkgCatalogUnavailable), 503);
        }

        return response()->json(['data' => $products]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $applications = InstallationApplication::query()->where('user_id', $request->user()->id)->latest()->get();

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
                    'INT' . now()->format('md'),
                ),
                'user_id' => $request->user()->id,
                'status' => RequestStatus::UnderReview->value,
            ]);

            $this->logActivity($request, 'create_broadband_application', [
                'broadband_application_id' => $application->id,
                'status' => $application->status->value,
            ]);

            return $application;
        });
        ServiceRequestSubmitted::dispatch('installation_application', $application->id);

        $this->sendTelegramStatusNotification($application, '📡 NEW BROADBAND INSTALLATION');

        return (new BroadbandApplicationResource($application))->additional(
            ApiMessage::attach(MessageCode::ReqSubmitted),
        );
    }

    public function update(
        UpdateBroadbandApplicationRequest $request,
        InstallationApplication $installationApplication,
    ): BroadbandApplicationResource {
        $this->ensureOwner($request, $installationApplication);

        $application = DB::transaction(function () use ($request, $installationApplication) {
            $validated = $request->validated();
            $installationApplication->update($validated);

            $this->logActivity($request, 'update_broadband_application', [
                'broadband_application_id' => $installationApplication->id,
            ]);

            return $installationApplication->refresh();
        });

        $this->sendTelegramStatusNotification($application, '✏️ BROADBAND INSTALLATION UPDATED');

        return (new BroadbandApplicationResource($application))->additional(
            ApiMessage::attach(MessageCode::ReqUpdated),
        );
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

        $application = $installationApplication->refresh();
        $this->sendTelegramStatusNotification($application, '❌ BROADBAND INSTALLATION CANCELLED');

        return (new BroadbandApplicationResource($application))->additional(
            ApiMessage::attach(MessageCode::ReqCancelled),
        );
    }

    private function sendTelegramStatusNotification(InstallationApplication $application, string $title): void
    {
        $detail = $application->package_detail ?? [];
        $fields = [
            '🔖 Service Type' => $application->service_type,
            '🎫 Ticket' => $application->ticket_number,
            '🗓️ Date' => $application->created_at?->format('d M Y, h:i A') ?? '—',
            '👤 Customer' => $application->name,
            '📞 Phone' => $application->phone,
            '📍 Address' => $application->address,
            '📦 Package' => (string) ($detail['product_line'] ?? ($detail['product_name'] ?? '—')),
            '⚡ Speed' => (string) ($detail['product_speed'] ?? '—'),
            '📅 Term' => trim(
                (string) ($detail['month_cycle'] ?? '—') . ' ' . (string) ($detail['month_cycle_unit'] ?? ''),
            ),
            '📝 Note' => $application->note ?? '—',
            '📋 Status' => $application->status->value,
        ];
        $message = "<b>{$title}</b>\n\n";

        foreach ($fields as $label => $value) {
            $message .= $label . ': ' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
        }

        try {
            $this->telegram->sendMessage($message);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function ensureOwner(Request $request, InstallationApplication $installationApplication): void
    {
        if ($installationApplication->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }
}
