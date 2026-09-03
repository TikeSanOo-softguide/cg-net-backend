<?php

namespace App\Http\Controllers\Api\Requests;

use App\Http\Controllers\Controller;
use App\Http\Requests\RelocationRequest\CreateRelocationRequest;
use App\Http\Requests\RelocationRequest\UpdateRelocationRequest;
use App\Http\Resources\RelocationRequest\RelocationRequestResource;
use App\Models\RelocationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelocationRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $relocations = RelocationRequest::query()
            ->where('user_id', $request->user()->id)->get();
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

        return new RelocationRequestResource($relocation);
    }

    public function show(RelocationRequest $relocationRequest): RelocationRequestResource
    {
        return new RelocationRequestResource($relocationRequest);
    }

    public function update(UpdateRelocationRequest $request, RelocationRequest $relocationRequest): RelocationRequestResource
    {
        $relocationRequest->update($request->validated());
        return new RelocationRequestResource($relocationRequest->refresh());
    }

    public function destroy(RelocationRequest $relocationRequest): JsonResponse
    {
        $relocationRequest->delete();

        return response()->json(null, 204);
    }
}
