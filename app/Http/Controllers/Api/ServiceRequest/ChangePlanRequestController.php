<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\MessageCode;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateChangePlanRequest;
use App\Http\Requests\ServiceRequest\UpdateChangePlanRequest;
use App\Http\Resources\ChangePlanRequest\ChangePlanRequestResource;
use App\Events\ServiceRequestSubmitted;
use App\Models\ChangePlanRequest;
use App\Models\Package;
use App\Services\Telegram\TelegramService;
use App\Support\ApiMessage;
use App\Support\GeneratesTicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChangePlanRequestController extends Controller
{
    public function __construct(private TelegramService $telegram) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = ChangePlanRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereHas(
                'user',
                fn($query) => $query->whereColumn(
                    'users.broadband_account_number',
                    'change_plan_requests.broadband_account_number',
                ),
            )
            ->latest()
            ->get();

        return ChangePlanRequestResource::collection($requests);
    }

    public function store(CreateChangePlanRequest $request): ChangePlanRequestResource
    {
        $changePlanRequest = DB::transaction(function () use ($request) {
            $validated = $this->fillPackageDetails($request->validated());

            $changePlanRequest = ChangePlanRequest::query()->create([
                ...$validated,
                'ticket_number' => GeneratesTicketNumber::next(
                    ChangePlanRequest::class,
                    'ticket_number',
                    'CPL' . now()->format('md'),
                ),
                'user_id' => $request->user()->id,
                'status' => 'under_review',
            ]);
            ServiceRequestSubmitted::dispatch('change_plan_request', $changePlanRequest->id);

            $this->logActivity($request, 'create_change_plan_request', [
                'change_plan_request_id' => $changePlanRequest->id,
                'status' => $changePlanRequest->status,
            ]);

            return $changePlanRequest;
        });

        $changePlanRequest->load('user');
        $this->sendTelegramStatusNotification($changePlanRequest, '📦 NEW CHANGE PLAN REQUEST');

        return (new ChangePlanRequestResource($changePlanRequest))->additional(
            ApiMessage::attach(MessageCode::ReqSubmitted),
        );
    }

    public function show(Request $request, ChangePlanRequest $changePlanRequest): ChangePlanRequestResource
    {
        $this->ensureOwner($request, $changePlanRequest);

        return new ChangePlanRequestResource($changePlanRequest);
    }

    public function update(
        UpdateChangePlanRequest $request,
        ChangePlanRequest $changePlanRequest,
    ): ChangePlanRequestResource {
        $this->ensureOwner($request, $changePlanRequest);
        $this->ensureUnderReview($changePlanRequest);

        $validated = $this->fillPackageDetails($request->validated());
        $changePlanRequest->update($validated);
        $this->logActivity($request, 'update_change_plan_request', [
            'change_plan_request_id' => $changePlanRequest->id,
        ]);

        $changePlanRequest->refresh()->load('user');
        $this->sendTelegramStatusNotification($changePlanRequest, '🔄 CHANGE PLAN REQUEST UPDATED');

        return (new ChangePlanRequestResource($changePlanRequest))->additional(
            ApiMessage::attach(MessageCode::ReqUpdated),
        );
    }

    public function cancel(Request $request, ChangePlanRequest $changePlanRequest): ChangePlanRequestResource
    {
        $this->ensureOwner($request, $changePlanRequest);
        $this->ensureUnderReview($changePlanRequest);

        $changePlanRequest->update([
            'status' => RequestStatus::Cancelled,
        ]);

        $this->logActivity($request, 'cancel_change_plan_request', [
            'change_plan_request_id' => $changePlanRequest->id,
        ]);

        $changePlanRequest->refresh()->load('user');
        $this->sendTelegramStatusNotification($changePlanRequest, '🚫 CHANGE PLAN REQUEST CANCELLED');

        return (new ChangePlanRequestResource($changePlanRequest))->additional(
            ApiMessage::attach(MessageCode::ReqCancelled),
        );
    }

    public function destroy(Request $request, ChangePlanRequest $changePlanRequest): JsonResponse
    {
        $this->ensureOwner($request, $changePlanRequest);
        $this->ensureUnderReview($changePlanRequest);

        $changePlanRequest->delete();
        $this->logActivity($request, 'delete_change_plan_request', [
            'change_plan_request_id' => $changePlanRequest->id,
        ]);

        return response()->json(ApiMessage::attach(MessageCode::ReqCancelled));
    }

    /**
     * Prefer server-authored package snapshots when package_id is present.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function fillPackageDetails(array $validated): array
    {
        foreach (['current_package_detail', 'new_package_detail'] as $key) {
            $packageId = data_get($validated, "{$key}.package_id");
            if ($packageId === null || $packageId === '') {
                continue;
            }

            $package = Package::query()->find($packageId);
            if ($package !== null) {
                $validated[$key] = $package->changePlanDetail();
            }
        }

        return $validated;
    }

    private function ensureOwner(Request $request, ChangePlanRequest $changePlanRequest): void
    {
        if ($changePlanRequest->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }

    private function ensureUnderReview(ChangePlanRequest $changePlanRequest): void
    {
        abort_unless($changePlanRequest->status === RequestStatus::UnderReview, 409);
    }

    private function sendTelegramStatusNotification(ChangePlanRequest $changePlanRequest, string $title): void
    {
        $currentPackage = $changePlanRequest->current_package_detail ?? [];
        $newPackage = $changePlanRequest->new_package_detail ?? [];
        $fields = [
            '• Ticket' => $changePlanRequest->ticket_number,
            '• Service Type' => $changePlanRequest->service_type ?? '—',
            '• Account' => $changePlanRequest->broadband_account_number,
            '• Customer' => $changePlanRequest->user?->name ?? 'Customer',
            '• Phone' => $changePlanRequest->contact_phone,
            '• Current Package' => data_get($currentPackage, 'network_name.en') ?? '—',
            '• Current Speed' =>
                data_get($currentPackage, 'speed.mbps') !== null
                    ? data_get($currentPackage, 'speed.mbps') . ' Mbps'
                    : '—',
            '• Current Price' => data_get($currentPackage, 'price') ?? '—',
            '• Current Billing Period' => $this->billingPeriodLabel($currentPackage),
            '• New Package' => data_get($newPackage, 'network_name.en') ?? '—',
            '• New Speed' =>
                data_get($newPackage, 'speed.mbps') !== null ? data_get($newPackage, 'speed.mbps') . ' Mbps' : '—',
            '• New Price' => data_get($newPackage, 'price') ?? '—',
            '• New Billing Period' => $this->billingPeriodLabel($newPackage),
            '• Preferred Date' => $changePlanRequest->preferred_date?->format('d M Y') ?? '—',
            '• Note' => $changePlanRequest->note ?? '—',
            '• Status' => $changePlanRequest->status->value,
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

    /**
     * @param  array<string, mixed>  $package
     */
    private function billingPeriodLabel(array $package): string
    {
        $term = data_get($package, 'term');
        if (!is_array($term)) {
            return '—';
        }

        $cycle = $term['cycle'] ?? null;
        $months = $term['months'] ?? null;
        $unit = $term['cycle_unit'] ?? ($months !== null ? 'months' : null);
        $period = $cycle ?? $months;

        if ($period === null || $period === '') {
            return '—';
        }

        return trim((string) $period . ' ' . (string) ($unit ?? ''));
    }
}
