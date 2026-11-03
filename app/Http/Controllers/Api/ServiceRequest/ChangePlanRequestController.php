<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\MessageCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateChangePlanRequest;
use App\Http\Requests\ServiceRequest\UpdateChangePlanRequest;
use App\Http\Resources\ChangePlanRequest\ChangePlanRequestResource;
use App\Events\ServiceRequestSubmitted;
use App\Models\ChangePlanRequest;
use App\Support\ApiMessage;
use App\Support\GeneratesTicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChangePlanRequestController extends Controller
{
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
            $changePlanRequest = ChangePlanRequest::query()->create([
                ...$request->validated(),
                'ticket_number' => GeneratesTicketNumber::next(
                    ChangePlanRequest::class,
                    'ticket_number',
                    'CPL'.now()->format('md'),
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

        return (new ChangePlanRequestResource($changePlanRequest))
            ->additional(ApiMessage::attach(MessageCode::ReqSubmitted));
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
        $changePlanRequest->update($request->validated());
        $this->logActivity($request, 'update_change_plan_request', [
            'change_plan_request_id' => $changePlanRequest->id,
        ]);

        return (new ChangePlanRequestResource($changePlanRequest->refresh()))
            ->additional(ApiMessage::attach(MessageCode::ReqUpdated));
    }

    public function destroy(Request $request, ChangePlanRequest $changePlanRequest): JsonResponse
    {
        $this->ensureOwner($request, $changePlanRequest);
        $changePlanRequest->delete();
        $this->logActivity($request, 'delete_change_plan_request', [
            'change_plan_request_id' => $changePlanRequest->id,
        ]);

        return response()->json(ApiMessage::attach(MessageCode::ReqCancelled));
    }

    private function ensureOwner(Request $request, ChangePlanRequest $changePlanRequest): void
    {
        if ($changePlanRequest->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }
}
