<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\Admin;
use App\Models\ChangePasswordRequest;
use App\Models\ChangePlanRequest;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\RelocationRequest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

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

        if ($users->count() < 8 || ! Admin::query()->exists()) {
            throw new RuntimeException('Run AdminSeeder and CustomerSeeder before ServiceRequestSeeder.');
        }

        $admin = Admin::query()->firstOrFail();

        $this->seedRequestType(InstallationApplication::class, 'SEED-INS', $users, $admin, function (
            User $user,
            Admin $admin,
            RequestStatus $status,
            string $ticketNumber,
            string $createdAt,
        ): void {
            InstallationApplication::factory()->create([
                'user_id' => $user->id,
                'contact_name' => $user->name,
                'contact_phone' => $user->phone,
                'ticket_number' => $ticketNumber,
                'status' => $status,
                'admin_id' => $status === RequestStatus::Approved ? $admin->id : null,
                'created_at' => $createdAt,
            ]);
        });

        $this->seedRequestType(FailureReport::class, 'SEED-FAIL', $users, $admin, function (
            User $user,
            Admin $admin,
            RequestStatus $status,
            string $ticketNumber,
            string $createdAt,
        ): void {
            FailureReport::factory()->create([
                'user_id' => $user->id,
                'broadband_account_number' => $user->broadband_account_number,
                'ticket_number' => $ticketNumber,
                'status' => $status,
                'admin_id' => $status === RequestStatus::Approved ? $admin->id : null,
                'created_at' => $createdAt,
            ]);
        });

        $this->seedRequestType(RelocationRequest::class, 'SEED-REL', $users, $admin, function (
            User $user,
            Admin $admin,
            RequestStatus $status,
            string $ticketNumber,
            string $createdAt,
        ): void {
            RelocationRequest::factory()->create([
                'user_id' => $user->id,
                'broadband_account_number' => $user->broadband_account_number,
                'ticket_number' => $ticketNumber,
                'status' => $status,
                'admin_id' => $status === RequestStatus::Approved ? $admin->id : null,
                'created_at' => $createdAt,
            ]);
        });

        $this->seedRequestType(ChangePlanRequest::class, 'SEED-PLAN', $users, $admin, function (
            User $user,
            Admin $admin,
            RequestStatus $status,
            string $ticketNumber,
            string $createdAt,
        ): void {
            ChangePlanRequest::factory()->create([
                'user_id' => $user->id,
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => str_starts_with((string) $user->broadband_account_number, 'CG') ? 'CG' : 'FTTH',
                'ticket_number' => $ticketNumber,
                'status' => $status,
                'admin_id' => $status === RequestStatus::Approved ? $admin->id : null,
                'created_at' => $createdAt,
            ]);
        });

        $this->seedRequestType(ChangePasswordRequest::class, 'SEED-PASS', $users, $admin, function (
            User $user,
            Admin $admin,
            RequestStatus $status,
            string $ticketNumber,
            string $createdAt,
        ): void {
            ChangePasswordRequest::factory()->create([
                'user_id' => $user->id,
                'broadband_account_number' => $user->broadband_account_number,
                'ticket_number' => $ticketNumber,
                'status' => $status,
                'admin_id' => null,
                'created_at' => $createdAt,
            ]);
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  Collection<int, User>  $users
     * @param  \Closure(User, Admin, RequestStatus, string, string): void  $create
     */
    private function seedRequestType(
        string $modelClass,
        string $ticketPrefix,
        Collection $users,
        Admin $admin,
        \Closure $create,
    ): void {
        $statuses = RequestStatus::cases();

        for ($number = 1; $number <= 100; $number++) {
            $ticketNumber = $ticketPrefix . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            if ($modelClass::query()->where('ticket_number', $ticketNumber)->exists()) {
                continue;
            }

            $user = $users->get(($number - 1) % $users->count());
            $status = $statuses[($number - 1) % count($statuses)];
            $createdAt = now()
                ->subDays((($number - 1) * 3) % 365)
                ->toDateTimeString();

            $create($user, $admin, $status, $ticketNumber, $createdAt);
        }
    }
}
