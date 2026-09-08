<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateChangePasswordRequest;
use App\Http\Requests\ServiceRequest\UpdateChangePasswordRequest;
use App\Http\Resources\ChangePasswordRequest\ChangePasswordRequestResource;
use App\Models\ChangePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChangePasswordRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = ChangePasswordRequest::query()->where('user_id', $request->user()->id)->latest()->get();

        return ChangePasswordRequestResource::collection($requests);
    }

    public function store(CreateChangePasswordRequest $request): ChangePasswordRequestResource
    {
        $validated = $request->validated();
        $password = ChangePasswordRequest::query()->create([...$validated, 'status' => 'under_review']);

        return new ChangePasswordRequestResource($password);
    }

    public function show(Request $request, ChangePasswordRequest $changePasswordRequest): ChangePasswordRequestResource
    {
        $this->ensureOwner($request, $changePasswordRequest);

        return new ChangePasswordRequestResource($changePasswordRequest);
    }

    public function update(
        UpdateChangePasswordRequest $request,
        ChangePasswordRequest $changePasswordRequest,
    ): ChangePasswordRequestResource {
        $this->ensureOwner($request, $changePasswordRequest);
        $changePasswordRequest->update($request->validated());

        return new ChangePasswordRequestResource($changePasswordRequest->refresh());
    }

    public function destroy(Request $request, ChangePasswordRequest $changePasswordRequest): \Illuminate\Http\Response
    {
        $this->ensureOwner($request, $changePasswordRequest);
        $changePasswordRequest->delete();

        return response()->noContent();
    }

    private function ensureOwner(Request $request, ChangePasswordRequest $ChangePasswordRequest): void
    {
        if ($ChangePasswordRequest->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }
}
