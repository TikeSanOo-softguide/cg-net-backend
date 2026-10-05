<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\CustomerPackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\ProfileResource;
use App\Models\CustomerPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('wallet');
        $customerPackages = $this->activePackages($user);

        return (new ProfileResource([
            'user' => $user,
            'wallet' => $user->wallet,
            'packages' => $customerPackages,
        ]))
            ->response()
            ->header('Cache-Control', 'private, no-store')
            ->header('Pragma', 'no-cache');
    }

    /** @return Collection<int, CustomerPackage> */
    private function activePackages(User $user): Collection
    {
        return $user
            ->customerPackages()
            ->with('package.network', 'package.speed', 'package.term')
            ->where('status', CustomerPackageStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('starts_at')
            ->get();
    }
}
