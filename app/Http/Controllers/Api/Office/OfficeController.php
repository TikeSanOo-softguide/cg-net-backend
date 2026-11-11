<?php

namespace App\Http\Controllers\Api\Office;

use App\Http\Controllers\Controller;
use App\Http\Resources\Office\OfficeResource;
use App\Models\Office;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return OfficeResource::collection(Office::query()->orderBy('created_at')->orderBy('id')->get());
    }
}
