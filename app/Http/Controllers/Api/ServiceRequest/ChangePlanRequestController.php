<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateChangePlanRequest;
use App\Http\Requests\ServiceRequest\UpdateChangePlanRequest;
use App\Http\Resources\ChangePlanRequest\ChangePlanRequestResource;
use App\Events\ServiceRequestSubmitted;
use App\Models\ChangePlanRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChangePlanRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = ChangePlanRequest::query()
            ->with([
                'currentPackage.network',
                'currentPackage.speed',
                'currentPackage.term',
                'newPackage.network',
                'newPackage.speed',
                'newPackage.term',
            ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return ChangePlanRequestResource::collection($requests);
    }

    public function store(CreateChangePlanRequest $request): ChangePlanRequestResource
    {
        $changePlanRequest = ChangePlanRequest::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => 'under_review',
        ]);
        ServiceRequestSubmitted::dispatch('change_plan_request', $changePlanRequest->id);

        $this->logActivity($request, 'create_change_plan_request', [
            'change_plan_request_id' => $changePlanRequest->id,
            'status' => $changePlanRequest->status,
        ]);

        return new ChangePlanRequestResource(
            $changePlanRequest->load([
                'currentPackage.network',
                'currentPackage.speed',
                'currentPackage.term',
                'newPackage.network',
                'newPackage.speed',
                'newPackage.term',
            ]),
        );
    }

    public function show(Request $request, ChangePlanRequest $changePlanRequest): ChangePlanRequestResource
    {
        $this->ensureOwner($request, $changePlanRequest);

        return new ChangePlanRequestResource(
            $changePlanRequest->load([
                'currentPackage.network',
                'currentPackage.speed',
                'currentPackage.term',
                'newPackage.network',
                'newPackage.speed',
                'newPackage.term',
            ]),
        );
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

        return new ChangePlanRequestResource(
            $changePlanRequest
                ->refresh()
                ->load([
                    'currentPackage.network',
                    'currentPackage.speed',
                    'currentPackage.term',
                    'newPackage.network',
                    'newPackage.speed',
                    'newPackage.term',
                ]),
        );
    }

    public function destroy(Request $request, ChangePlanRequest $changePlanRequest): \Illuminate\Http\Response
    {
        $this->ensureOwner($request, $changePlanRequest);
        $changePlanRequest->delete();
        $this->logActivity($request, 'delete_change_plan_request', [
            'change_plan_request_id' => $changePlanRequest->id,
        ]);

        return response()->noContent();
    }

    private function ensureOwner(Request $request, ChangePlanRequest $changePlanRequest): void
    {
        if ($changePlanRequest->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }
}
