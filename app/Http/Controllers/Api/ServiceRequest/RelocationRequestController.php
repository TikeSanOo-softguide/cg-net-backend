<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\MessageCode;
use App\Enums\RequestStatus;
use App\Events\ServiceRequestSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateRelocationRequest;
use App\Http\Requests\ServiceRequest\UpdateRelocationRequest;
use App\Http\Resources\RelocationRequest\RelocationRequestResource;
use App\Models\RelocationRequest;
use App\Support\ApiMessage;
use App\Support\GeneratesTicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Services\Telegram\TelegramService;

class RelocationRequestController extends Controller
{
    public function __construct(private TelegramService $telegram) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $relocations = RelocationRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('relocation_requests.deleted_at')
            ->whereHas(
                'user',
                fn($query) => $query->whereColumn(
                    'users.broadband_account_number',
                    'relocation_requests.broadband_account_number',
                ),
            )
            ->latest()
            ->get();

        return RelocationRequestResource::collection($relocations);
    }

    public function store(CreateRelocationRequest $request): RelocationRequestResource
    {
        $relocation = DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $relocation = RelocationRequest::query()->create([
                ...$validated,
                'user_id' => $request->user()->id,
                'status' => RequestStatus::UnderReview->value,
                'ticket_number' => GeneratesTicketNumber::next(
                    RelocationRequest::class,
                    'ticket_number',
                    'RLC' . now()->format('md'),
                ),
            ]);

            ServiceRequestSubmitted::dispatch('relocation_request', $relocation->id);

            $this->logActivity($request, 'create_relocation_request', [
                'relocation_request_id' => $relocation->id,
                'status' => $relocation->status->value,
            ]);

            return $relocation;
        });

        $relocation->load(['user:id,name,phone', 'admin:id,username']);
        $this->sendTelegramStatusNotification($relocation, '📦 New Relocation Request');

        return (new RelocationRequestResource($relocation))->additional(ApiMessage::attach(MessageCode::ReqSubmitted));
    }

    public function show(Request $request, RelocationRequest $relocationRequest): RelocationRequestResource
    {
        $this->ensureOwner($request, $relocationRequest);
        return new RelocationRequestResource($relocationRequest);
    }

    public function update(
        UpdateRelocationRequest $request,
        RelocationRequest $relocationRequest,
    ): RelocationRequestResource {
        $this->ensureOwner($request, $relocationRequest);

        $relocation = DB::transaction(function () use ($request, $relocationRequest) {
            $relocationRequest->update($request->validated());
            $this->logActivity($request, 'update_relocation_request', [
                'relocation_request_id' => $relocationRequest->id,
                'status' => $relocationRequest->status,
            ]);
            return $relocationRequest->refresh();
        });

        $this->sendTelegramStatusNotification($relocation, '✏️ RELOCATION REQUEST UPDATED');

        return (new RelocationRequestResource($relocation))->additional(ApiMessage::attach(MessageCode::ReqUpdated));
    }

    public function cancel(Request $request, RelocationRequest $relocationRequest): RelocationRequestResource
    {
        $this->ensureOwner($request, $relocationRequest);
        $relocationRequest->update([
            'status' => RequestStatus::Cancelled,
        ]);
        $this->logActivity($request, 'cancel_relocation_request', [
            'relocation_request_id' => $relocationRequest->id,
        ]);

        $relocation = $relocationRequest->refresh();
        $this->sendTelegramStatusNotification($relocation, '❌ RELOCATION REQUEST CANCELLED');

        return (new RelocationRequestResource($relocation))->additional(ApiMessage::attach(MessageCode::ReqCancelled));
    }

    public function destroy(Request $request, RelocationRequest $relocationRequest): JsonResponse
    {
        $this->ensureOwner($request, $relocationRequest);
        $relocationRequest->delete();
        $this->logActivity($request, 'delete_relocation_request', [
            'relocation_request_id' => $relocationRequest->id,
        ]);
        return response()->json(null, 204);
    }

    private function ensureOwner(Request $request, RelocationRequest $relocationRequest): void
    {
        if ($relocationRequest->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }

    private function sendTelegramStatusNotification(RelocationRequest $relocation, string $title): void
    {
        $fields = [
            '🎫 Ticket' => $relocation->ticket_number,
            '🛜 Broadband Account' => $relocation->broadband_account_number,
            '👤 Customer' => $relocation->user?->name ?? 'Customer',
            '📞 Phone' => $relocation->phone,
            '📍 Current Address' => $relocation->current_address,
            '🏠 New Address' => $relocation->new_address,
            '📅 Preferred Date' => $relocation->preferred_date?->format('d M Y') ?? '—',
            '📝 Note' => $relocation->details ?? '—',
            '📋 Status' => $relocation->status->value,
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
}
