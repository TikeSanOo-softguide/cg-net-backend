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

class RelocationRequestController extends Controller
{
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
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $relocation = RelocationRequest::query()->create([
                ...$validated,
                'user_id' => $request->user()->id,
                'status' => 'under_review',
                'ticket_number' => GeneratesTicketNumber::next(
                    RelocationRequest::class,
                    'ticket_number',
                    'RLC' . now()->format('md'),
                ),
            ]);
            ServiceRequestSubmitted::dispatch('relocation_request', $relocation->id);
            $this->logActivity($request, 'create_relocation_request', [
                'relocation_request_id' => $relocation->id,
                'status' => $relocation->status,
            ]);
            return (new RelocationRequestResource($relocation))->additional(
                ApiMessage::attach(MessageCode::ReqSubmitted),
            );
        });
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

        return DB::transaction(function () use ($request, $relocationRequest) {
            $relocationRequest->update($request->validated());
            $this->logActivity($request, 'update_relocation_request', [
                'relocation_request_id' => $relocationRequest->id,
                'status' => $relocationRequest->status,
            ]);
            return (new RelocationRequestResource($relocationRequest->refresh()))->additional(
                ApiMessage::attach(MessageCode::ReqUpdated),
            );
        });
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

        return new RelocationRequestResource($relocationRequest->refresh());
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
}
