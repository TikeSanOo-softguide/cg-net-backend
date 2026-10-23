<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\CustomerPackageStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\DeactivateAccountRequest;
use App\Http\Requests\Api\Customer\UpdateLanguageRequest;
use App\Http\Requests\Api\Customer\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Api\Customer\UpdateProfileRequest;
use App\Http\Resources\Customer\ProfileResource;
use App\Models\CustomerPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update(['name' => $request->validated('name')]);

        return $this->show($request);
    }

    public function deactivate(DeactivateAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($request->validated('password'), $user->getAuthPassword())) {
            return response()->json(
                [
                    'message' => 'The provided password is incorrect.',
                    'errors' => ['password' => ['The provided password is incorrect.']],
                ],
                422,
            );
        }

        $user->update(['status' => UserStatus::Deactivated]);

        return response()->json(['message' => 'Account deactivated successfully.']);
    }

    public function updateLanguage(UpdateLanguageRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update(['lang' => $request->validated('lang')]);

        return response()->json([
            'data' => [
                'lang' => $user->lang,
            ],
        ]);
    }

    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'announcements_enabled' => $user->announcements_enabled,
            'system_enabled' => $user->system_enabled,
            'promotion_enabled' => $user->promotion_enabled,
            'general_enabled' => $user->general_enabled,
        ]);
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => [
                'announcements_notifications' => $user->announcements_enabled,
                'system_notifications' => $user->system_enabled,
                'promotion_notifications' => $user->promotion_enabled,
                'general_notifications' => $user->general_enabled,
            ],
        ]);
    }

    /** @return Collection<int, CustomerPackage> */
    private function activePackages(User $user): Collection
    {
        return $user
            ->customerPackages()
            ->with('package.network', 'package.speed', 'package.term', 'packageOrder')
            ->where('status', CustomerPackageStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('starts_at')
            ->get();
    }

    public function packageCredentials(Request $request, int $customerPackageId): JsonResponse
    {
        $customerPackage = CustomerPackage::query()
            ->whereKey($customerPackageId)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        return response()
            ->json([
                'data' => [
                    'credentials' => [
                        'account' => $customerPackage->username,
                        'password' => $customerPackage->password,
                    ],
                ],
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Pragma', 'no-cache');
    }
}
