<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\Package\TermResource;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermController extends Controller
{
    /**
     * GET /terms
     */
    public function index(Request $request): JsonResource
    {
        $terms = Term::query()->orderBy('months')->get();

        return TermResource::collection($terms);
    }
}
