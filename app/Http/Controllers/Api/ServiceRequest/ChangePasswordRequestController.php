<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\MessageCode;
use App\Enums\RequestStatus;
use App\Events\ServiceRequestSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateChangePasswordRequest;
use App\Http\Requests\ServiceRequest\UpdateChangePasswordRequest;
use App\Http\Resources\ChangePasswordRequest\ChangePasswordRequestResource;
use App\Models\ChangePasswordRequest;
use App\Support\ApiMessage;
use App\Support\GeneratesTicketNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChangePasswordRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = ChangePasswordRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('change_password_requests.deleted_at')
            ->whereHas('user', fn ($query) => $query->whereColumn(
                'users.broadband_account_number',
                'change_password_requests.broadband_account_number',
            ))
            ->latest()
            ->get();

        return ChangePasswordRequestResource::collection($requests);
    }

    public function store(CreateChangePasswordRequest $request): ChangePasswordRequestResource
    {
        $password = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $password = ChangePasswordRequest::query()->create([
                ...$validated,
                'ticket_number' => GeneratesTicketNumber::next(
                    ChangePasswordRequest::class,
                    'ticket_number',
                    'CWP'.now()->format('md'),
                ),
                'user_id' => $request->user()->id,
                'status' => 'under_review',
            ]);
            ServiceRequestSubmitted::dispatch('change_password_request', $password->id);
            $this->logActivity($request, 'create_change_password_request', [
                'change_password_request_id' => $password->id,
                'status' => $password->status,
            ]);

            return $password;
        });

        return (new ChangePasswordRequestResource($password))
            ->additional(ApiMessage::attach(MessageCode::ReqSubmitted));
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
        $this->logActivity($request, 'update_change_password_request', [
            'change_password_request_id' => $changePasswordRequest->id,
        ]);

        return (new ChangePasswordRequestResource($changePasswordRequest->refresh()))
            ->additional(ApiMessage::attach(MessageCode::ReqUpdated));
    }

    public function cancel(Request $request, ChangePasswordRequest $changePasswordRequest): ChangePasswordRequestResource
    {
        $this->ensureOwner($request, $changePasswordRequest);
        $this->ensureUnderReview($changePasswordRequest);

        $changePasswordRequest->update([
            'status' => RequestStatus::Cancelled,
        ]);
        $this->logActivity($request, 'cancel_change_password_request', [
            'change_password_request_id' => $changePasswordRequest->id,
        ]);

        return (new ChangePasswordRequestResource($changePasswordRequest->refresh()))
            ->additional(ApiMessage::attach(MessageCode::ReqCancelled));
    }

    public function destroy(Request $request, ChangePasswordRequest $changePasswordRequest): \Illuminate\Http\Response
    {
        $this->ensureOwner($request, $changePasswordRequest);
        $changePasswordRequest->delete();
        $this->logActivity($request, 'delete_change_password_request', [
            'change_password_request_id' => $changePasswordRequest->id,
        ]);

        return response()->noContent();
    }

    private function ensureOwner(Request $request, ChangePasswordRequest $ChangePasswordRequest): void
    {
        if ($ChangePasswordRequest->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }

    private function ensureUnderReview(ChangePasswordRequest $changePasswordRequest): void
    {
        abort_unless($changePasswordRequest->status === RequestStatus::UnderReview, 409);
    }
}
