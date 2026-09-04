<?php

namespace App\Http\Controllers\Api\ServiceRequest;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\CreateFailureReport;
use App\Http\Requests\ServiceRequest\UpdateFailureReport;
use App\Http\Resources\FailureReport\FailureReportResource;
use App\Models\FailureReport;
use App\Support\StoresPublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FailureReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $failureReports = FailureReport::query()->with('photos')
            ->where('user_id', $request->user()->id)
            ->get();
        return FailureReportResource::collection($failureReports);
    }

    public function show(FailureReport $failureReport): FailureReportResource
    {
        return new FailureReportResource($failureReport);
    }

    public function store(CreateFailureReport $request): FailureReportResource
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $failureReport = FailureReport::query()->create([
                'user_id' => $validated['user_id'],
                'broadband_account_id' => $validated['broadband_account_id'],
                'failure_type' => $validated['failure_type'],
                'description' => $validated['description'],
                'contact_name' => $validated['contact_name'],
                'contact_phone' => $validated['contact_phone'],
                'status' => 'under_review',
            ]);

            foreach ($request->file('photos') as $photo) {
                $imageUrl = StoresPublicImage::store(
                    $photo,
                    'service-request/failure-reports'
                );

                $failureReport->photos()->create([
                    'image_url' => $imageUrl,
                ]);
            }

            return new FailureReportResource(
                $failureReport->load('photos')
            );
        });
    }

    public function update(
        UpdateFailureReport $request,
        FailureReport $failureReport
    ): FailureReportResource {
        return DB::transaction(function () use ($request, $failureReport) {
            $validated = $request->validated();
            $failureReport->update([
                'user_id' => $validated['user_id'],
                'broadband_account_id' => $validated['broadband_account_id'],
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
                        $existingPhoto?->image_url
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
            }
            return new FailureReportResource(
                $failureReport->load('photos')
            );
        });
    }

    public function destroy(FailureReport $failureReport): Response
    {
        DB::transaction(function () use ($failureReport) {
            foreach ($failureReport->photos as $photo) {
                StoresPublicImage::delete($photo->image_url);
                $photo->delete();
            }
            $failureReport->delete();
        });
        return response()->noContent();
    }
}
