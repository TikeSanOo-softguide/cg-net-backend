<?php

namespace Database\Seeders;

use App\Enums\CustomerPackageStatus;
use App\Enums\RequestStatus;
use App\Models\Admin;
use App\Models\Area;
use App\Models\ChangePasswordRequest;
use App\Models\ChangePlanRequest;
use App\Models\InstallationApplication;
use App\Models\Package;
use App\Models\RelocationRequest;
use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ServiceRequestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @param  list<int>  $userIds
     */
    public function run(array $userIds = []): void
    {
        echo "Service request seeder started\n";

        $users = $userIds
            ? User::query()->whereIn('id', $userIds)->orderBy('id')->get()
            : User::query()->orderByDesc('id')->limit(20)->get()->sortBy('id')->values();
        $areas = Area::query()->get();
        $packages = Package::query()->get();

        if ($users->count() < 8 || $areas->isEmpty() || $packages->isEmpty() || !Admin::query()->exists()) {
            throw new \RuntimeException(
                'Run AdminSeeder, AreaSeeder, PackageSeeder, and CustomerSeeder before ServiceRequestSeeder.',
            );
        }

        $this->seedRequests($users, $areas, $packages);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     * @param  \Illuminate\Database\Eloquent\Collection<int, Area>  $areas
     * @param  Collection<int, Package>  $packages
     */
    private function seedRequests($users, $areas, $packages): void
    {
        $sample = $users->take(8)->values();
        $admin = Admin::query()->first();
        $areasByRegion = $areas->groupBy('region_id')->values();
        $installationStatuses = [RequestStatus::UnderReview, RequestStatus::Approved, RequestStatus::Cancelled];
        $today = now();

        foreach (range(0, 29) as $index) {
            $user = $sample[$index % $sample->count()];
            $area = $areasByRegion[$index % $areasByRegion->count()]->random();
            $createdAt = $today
                ->copy()
                ->subDays(29 - $index)
                ->setTime(min(12, $today->hour), 0);

            $application = InstallationApplication::factory()->create([
                'user_id' => $user->id,
                'area_id' => $area->id,
                'package_id' => $packages->random()->id,
                'status' => $installationStatuses[$index % count($installationStatuses)],
            ]);

            $application->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $relocUser = $sample[6];
        RelocationRequest::factory()->create([
            'user_id' => $relocUser->id,
            'broadband_account_number' => $relocUser->broadband_account_number,
            'status' => RequestStatus::UnderReview,
        ]);
        RelocationRequest::factory()->create([
            'user_id' => $sample[7]->id,
            'broadband_account_number' => $sample[7]->broadband_account_number,
            'status' => RequestStatus::Approved,
        ]);

        foreach ($users->take(20)->values() as $index => $user) {
            $customerPackage = $user
                ->customerPackages()
                ->where('status', CustomerPackageStatus::Active->value)
                ->first();

            if (!$customerPackage) {
                continue;
            }

            $currentPackageId = $customerPackage->package_id;
            $currentPackage = Package::query()->with('speed')->find($currentPackageId);

            $condition = $index % 3;
            $status = $index < 10
                ? RequestStatus::UnderReview
                : ($index < 17 ? RequestStatus::Approved : RequestStatus::Cancelled);

            $newPackage = match ($condition) {
                0 => $currentPackage?->speed
                    ? Package::query()
                        ->whereKeyNot($currentPackageId)
                        ->whereHas('speed', fn($query) => $query->where('mbps', '<', $currentPackage->speed->mbps))
                        ->first()
                    : null,
                1 => $currentPackage?->speed
                    ? Package::query()
                        ->whereKeyNot($currentPackageId)
                        ->whereHas('speed', fn($query) => $query->where('mbps', '>', $currentPackage->speed->mbps))
                        ->first()
                    : null,
                default => $currentPackage?->speed
                    ? Package::query()
                        ->whereKeyNot($currentPackageId)
                        ->where('network_id', '!=', $currentPackage->network_id)
                        ->whereHas('speed', fn($query) => $query->where('mbps', $currentPackage->speed->mbps))
                        ->first()
                    : null,
            };

            $newPackage ??= Package::query()->whereKeyNot($currentPackageId)->first();

            if (!$newPackage) {
                continue;
            }

            $adminId = $status === RequestStatus::Approved ? $admin?->id : null;
            do {
                $contactPhone = MyanmarFake::phone();
            } while ($contactPhone === $user->phone);

            ChangePlanRequest::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'broadband_account_number' => $user->broadband_account_number,
                    'current_package_id' => $currentPackageId,
                    'new_package_id' => $newPackage->id,
                    'preferred_date' => now()
                        ->{$index < 2 ? 'subDays' : 'addDays'}($index + 1)
                        ->toDateString(),
                ],
                [
                    'broadband_account_number' => $user->broadband_account_number,
                    'contact_name' => $user->name,
                    'contact_phone' => $contactPhone,
                    'note' => 'Seeded change plan request.',
                    'status' => $status,
                    'admin_id' => $adminId,
                ],
            );
        }

        ChangePasswordRequest::factory()->count(10)->underReview()->create();
        ChangePasswordRequest::factory()->count(6)->approved()->create();
        ChangePasswordRequest::factory()->count(4)->cancelled()->create();
    }
}
