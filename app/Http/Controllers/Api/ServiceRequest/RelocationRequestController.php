<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateRelocationRequest;
use App\Http\Requests\ServiceRequest\UpdateRelocationRequest;
use App\Http\Resources\RelocationRequest\RelocationRequestResource;
use App\Models\RelocationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RelocationRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $relocations = RelocationRequest::query()->where('user_id', $request->user()->id)->get();
        return RelocationRequestResource::collection($relocations);
    }

    public function store(CreateRelocationRequest $request): RelocationRequestResource
    {
        $validated = $request->validated();

        $relocation = RelocationRequest::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'under_review',
        ]);
        $this->logActivity($request, 'create_relocation_request', [
            'relocation_request_id' => $relocation->id,
            'status' => $relocation->status,
        ]);

        return new RelocationRequestResource($relocation);
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
        $relocationRequest->update($request->validated());
        $this->logActivity($request, 'update_relocation_request', [
            'relocation_request_id' => $relocationRequest->id,
            'status' => $relocationRequest->status,
        ]);
        return new RelocationRequestResource($relocationRequest->refresh());
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
