<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Enums\MessageCode;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateFailureReport;
use App\Http\Requests\ServiceRequest\UpdateFailureReport;
use App\Http\Resources\FailureReport\FailureReportResource;
use App\Events\ServiceRequestSubmitted;
use App\Models\FailureReport;
use App\Support\ApiMessage;
use App\Support\GeneratesTicketNumber;
use App\Support\StoresPublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FailureReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $failureReports = FailureReport::query()
            ->with('photos')
            ->where('user_id', $request->user()->id)
            ->whereNull('failure_reports.deleted_at')
            ->whereHas(
                'user',
                fn($query) => $query->whereColumn(
                    'users.broadband_account_number',
                    'failure_reports.broadband_account_number',
                ),
            )
            ->latest()
            ->get();

        return FailureReportResource::collection($failureReports);
    }

    public function show(Request $request, FailureReport $failureReport): FailureReportResource
    {
        $this->ensureOwner($request, $failureReport);
        return new FailureReportResource($failureReport);
    }

    public function store(CreateFailureReport $request): FailureReportResource
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $failureReport = FailureReport::query()->create([
                'user_id' => $request->user()->id,
                'broadband_account_number' => $validated['broadband_account_number'],
                'failure_type' => $validated['failure_type'],
                'description' => $validated['description'],
                'contact_name' => $validated['contact_name'],
                'contact_phone' => $validated['contact_phone'],
                'ticket_number' => GeneratesTicketNumber::next(
                    FailureReport::class,
                    'ticket_number',
                    'FLR'.now()->format('md'),
                ),
                'status' => 'under_review',
            ]);
            ServiceRequestSubmitted::dispatch('failure_report', $failureReport->id);

            foreach ($request->file('photos', []) as $photo) {
                $imageUrl = StoresPublicImage::store($photo, 'service-request/failure-reports');

                $failureReport->photos()->create([
                    'image_url' => $imageUrl,
                ]);
            }

            $this->logActivity($request, 'create_failure_report', [
                'failure_report_id' => $failureReport->id,
                'failure_type' => $failureReport->failure_type,
            ]);

            return (new FailureReportResource($failureReport->load('photos')))
                ->additional(ApiMessage::attach(MessageCode::ReqSubmitted));
        });
    }

    public function update(UpdateFailureReport $request, FailureReport $failureReport): FailureReportResource
    {
        $this->ensureOwner($request, $failureReport);
        return DB::transaction(function () use ($request, $failureReport) {
            $validated = $request->validated();
            $failureReport->update([
                'broadband_account_number' =>
                    $validated['broadband_account_number'] ?? $failureReport->broadband_account_number,
                'failure_type' => $validated['failure_type'],
                'description' => $validated['description'],
                'contact_name' => $validated['contact_name'],
                'contact_phone' => $validated['contact_phone'],
            ]);

            if ($request->hasFile('photos')) {
                $photos = $request->file('photos');
                $existingPhotos = $failureReport->photos()->get();
                foreach ($photos as $index => $photo) {
                    $existingPhoto = $existingPhotos->get($index);
                    $imageUrl = StoresPublicImage::store(
                        $photo,
                        'service-request/failure-reports',
                        $existingPhoto?->image_url,
                    );
                    if ($existingPhoto) {
                        $existingPhoto->update([
                            'image_url' => $imageUrl,
                        ]);
                    } else {
                        $failureReport->photos()->create([
                            'image_url' => $imageUrl,
                        ]);
                    }
                }

                foreach ($existingPhotos->slice(count($photos)) as $existingPhoto) {
                    StoresPublicImage::delete($existingPhoto->image_url);
                    $existingPhoto->delete();
                }
            }

            $this->logActivity($request, 'update_failure_report', [
                'failure_report_id' => $failureReport->id,
            ]);
            return (new FailureReportResource($failureReport->load('photos')))
                ->additional(ApiMessage::attach(MessageCode::ReqUpdated));
        });
    }

    public function cancel(Request $request, FailureReport $failureReport): FailureReportResource
    {
        $this->ensureOwner($request, $failureReport);
        $failureReport->update([
            'status' => RequestStatus::Cancelled,
        ]);

        $this->logActivity($request, 'cancel_failure_report', [
            'failure_report_id' => $failureReport->id,
        ]);

        return (new FailureReportResource($failureReport->refresh()->load('photos')))
            ->additional(ApiMessage::attach(MessageCode::ReqCancelled));
    }

    public function destroy(Request $request, FailureReport $failureReport): Response
    {
        $this->ensureOwner($request, $failureReport);
        DB::transaction(function () use ($failureReport) {
            foreach ($failureReport->photos as $photo) {
                StoresPublicImage::delete($photo->image_url);
                $photo->delete();
            }
            $failureReport->delete();
        });

        $this->logActivity($request, 'delete_failure_report', [
            'failure_report_id' => $failureReport->id,
        ]);
        return response()->noContent();
    }

    private function ensureOwner(Request $request, FailureReport $failureReport): void
    {
        if ($failureReport->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException();
        }
    }
}
