<?php

namespace Tests\Feature;

use App\Enums\FailureType;
use App\Models\Area;
use App\Models\ChangePasswordRequest;
use App\Models\ChangePlanRequest;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\Package;
use App\Models\RelocationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_apis_require_authentication(): void
    {
        foreach ([
            '/api/failure-reports',
            '/api/change-plan-requests',
            '/api/change-password-requests',
            '/api/relocation-requests',
            '/api/installation-application',
        ] as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        foreach ([
            '/api/failure-reports/create',
            '/api/change-plan-requests/create',
            '/api/change-password-requests/create',
            '/api/relocation-requests/create',
            '/api/installation-application/create',
        ] as $endpoint) {
            $this->postJson($endpoint)->assertUnauthorized();
        }
    }

    public function test_customer_can_create_update_cancel_and_delete_a_broadband_application(): void
    {
        Storage::fake('public');
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('service-requests')->plainTextToken;
        $package = Package::factory()->create();
        $area = Area::factory()->create();
        $applicationData = [
            'package_id' => $package->id,
            'area_id' => $area->id,
            'id_type' => 'NRC',
            'id_name' => 'Test Customer',
            'id_number' => '12/ABC(N)123456',
            'address' => 'Customer address',
            'phone' => '95912345678',
            'note' => 'Please install soon.',
            'photos' => [
                UploadedFile::fake()->image('id-front.jpg'),
                UploadedFile::fake()->image('id-back.jpg'),
            ],
        ];

        $response = $this->withToken($token)
            ->post('/api/installation-application/create', $applicationData, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.package_id', $package->id)
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonCount(2, 'data.photos');
        $application = InstallationApplication::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)
            ->getJson('/api/installation-application')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/installation-application/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id);

        $this->withToken($token)
            ->put('/api/installation-application/'.$application->id, [
                ...$applicationData,
                'id_name' => 'Updated Customer',
                'address' => 'Updated customer address',
                'photos' => [
                    UploadedFile::fake()->image('updated-front.jpg'),
                    UploadedFile::fake()->image('updated-back.jpg'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id_name', 'Updated Customer')
            ->assertJsonPath('data.address', 'Updated customer address')
            ->assertJsonCount(2, 'data.photos');

        $this->withToken($token)
            ->patchJson('/api/installation-application/'.$application->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->withToken($token)
            ->deleteJson('/api/installation-application/'.$application->id)
            ->assertNoContent();

        $this->assertSoftDeleted('installation_applications', ['id' => $application->id]);
    }

    public function test_customer_can_create_update_cancel_and_delete_a_relocation_request(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG62345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $requestData = [
            'broadband_account_number' => $user->broadband_account_number,
            'current_address' => 'Current address',
            'new_address' => 'New address',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'phone' => '95912345678',
            'details' => 'Please call before the relocation.',
        ];

        $response = $this->withToken($token)->postJson('/api/relocation-requests/create', $requestData)
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.broadband_account_number', $user->broadband_account_number)
            ->assertJsonPath('data.status', 'under_review');
        $relocationRequest = RelocationRequest::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)
            ->getJson('/api/relocation-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->putJson('/api/relocation-requests/'.$relocationRequest->id, [
                ...$requestData,
                'new_address' => 'Updated new address',
                'details' => 'Updated relocation details.',
            ])
            ->assertOk()
            ->assertJsonPath('data.new_address', 'Updated new address')
            ->assertJsonPath('data.details', 'Updated relocation details.');

        $this->withToken($token)
            ->patchJson('/api/relocation-requests/'.$relocationRequest->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->withToken($token)
            ->deleteJson('/api/relocation-requests/'.$relocationRequest->id)
            ->assertNoContent();

        $this->assertSoftDeleted('relocation_requests', ['id' => $relocationRequest->id]);
    }

    public function test_customer_can_create_update_cancel_and_delete_a_failure_report(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $createData = [
            'broadband_account_number' => $user->broadband_account_number,
            'failure_type' => FailureType::NoInternet->value,
            'description' => 'The connection has been unavailable since this morning.',
            'contact_name' => 'Test Customer',
            'contact_phone' => '95912345678',
            'photos' => [
                UploadedFile::fake()->image('router-1.jpg'),
                UploadedFile::fake()->image('router-2.jpg'),
                UploadedFile::fake()->image('router-3.jpg'),
            ],
        ];

        $response = $this->withToken($token)
            ->post('/api/failure-reports/create', $createData, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.broadband_account_number', $user->broadband_account_number)
            ->assertJsonPath('data.failure_type', FailureType::NoInternet->value)
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonCount(3, 'data.photos');

        $failureReport = FailureReport::query()->findOrFail($response->json('data.id'));
        $this->assertDatabaseCount('failure_photos', 3);

        $this->withToken($token)
            ->getJson('/api/failure-reports')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/failure-reports/'.$failureReport->id)
            ->assertOk()
            ->assertJsonPath('data.id', $failureReport->id);

        $this->withToken($token)
            ->putJson('/api/failure-reports/'.$failureReport->id, [
                ...$createData,
                'photos' => null,
                'failure_type' => FailureType::Slow->value,
                'description' => 'The connection is slow.',
            ])
            ->assertOk()
            ->assertJsonPath('data.failure_type', FailureType::Slow->value)
            ->assertJsonPath('data.description', 'The connection is slow.');

        $this->withToken($token)
            ->patchJson('/api/failure-reports/'.$failureReport->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->withToken($token)
            ->deleteJson('/api/failure-reports/'.$failureReport->id)
            ->assertNoContent();

        $this->assertSoftDeleted('failure_reports', ['id' => $failureReport->id]);
    }

    public function test_customer_can_create_update_and_delete_a_change_plan_request(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG22345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $currentPackage = Package::factory()->create();
        $newPackage = Package::factory()->create();
        $requestData = [
            'user_id' => $user->id,
            'broadband_account_number' => $user->broadband_account_number,
            'current_package_id' => $currentPackage->id,
            'new_package_id' => $newPackage->id,
            'preferred_date' => now()->addDays(3)->toDateString(),
            'contact_name' => 'Test Customer',
            'contact_phone' => '95912345678',
            'note' => 'Please change my package.',
        ];

        $response = $this->withToken($token)->postJson('/api/change-plan-requests/create', $requestData)
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'under_review');
        $changePlanRequest = ChangePlanRequest::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)
            ->getJson('/api/change-plan-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/change-plan-requests/'.$changePlanRequest->id)
            ->assertOk()
            ->assertJsonPath('data.id', $changePlanRequest->id);

        $this->withToken($token)
            ->putJson('/api/change-plan-requests/'.$changePlanRequest->id, [
                ...$requestData,
                'new_package_id' => Package::factory()->create()->id,
                'note' => 'Please apply the updated package.',
            ])
            ->assertOk()
            ->assertJsonPath('data.note', 'Please apply the updated package.');

        $this->withToken($token)
            ->deleteJson('/api/change-plan-requests/'.$changePlanRequest->id)
            ->assertNoContent();

        $this->assertSoftDeleted('change_plan_requests', ['id' => $changePlanRequest->id]);
    }

    public function test_customer_can_create_update_and_delete_a_change_password_request(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG32345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $requestData = [
            'user_id' => $user->id,
            'broadband_account_number' => $user->broadband_account_number,
            'new_wifi_name' => 'UpdatedWifi',
            'new_password' => 'updated-secret',
            'contact_name' => 'Test Customer',
            'contact_phone' => '95912345678',
        ];

        $response = $this->withToken($token)->postJson('/api/change-password-requests/create', $requestData)
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'under_review');
        $changePasswordRequest = ChangePasswordRequest::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)
            ->getJson('/api/change-password-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/change-password-requests/'.$changePasswordRequest->id)
            ->assertOk()
            ->assertJsonPath('data.id', $changePasswordRequest->id);

        $this->withToken($token)
            ->putJson('/api/change-password-requests/'.$changePasswordRequest->id, [
                ...$requestData,
                'new_wifi_name' => 'NewWifi',
                'new_password' => 'another-secret',
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.new_wifi_name', 'NewWifi')
            ->assertJsonPath('data.status', 'approved');

        $this->withToken($token)
            ->deleteJson('/api/change-password-requests/'.$changePasswordRequest->id)
            ->assertNoContent();

        $this->assertSoftDeleted('change_password_requests', ['id' => $changePasswordRequest->id]);
    }

    public function test_customers_cannot_view_another_users_service_requests(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG42345678']);
        $otherUser = User::factory()->create(['broadband_account_number' => 'CG52345678']);
        $token = $user->createToken('service-requests')->plainTextToken;

        $failureReport = FailureReport::factory()->create([
            'user_id' => $otherUser->id,
            'broadband_account_number' => $otherUser->broadband_account_number,
            'admin_id' => null,
        ]);
        $changePlanRequest = ChangePlanRequest::factory()->create([
            'user_id' => $otherUser->id,
            'broadband_account_number' => $otherUser->broadband_account_number,
            'admin_id' => null,
        ]);
        $changePasswordRequest = ChangePasswordRequest::factory()->create([
            'user_id' => $otherUser->id,
            'broadband_account_number' => $otherUser->broadband_account_number,
            'admin_id' => null,
        ]);
        $relocationRequest = RelocationRequest::factory()->create([
            'user_id' => $otherUser->id,
            'broadband_account_number' => $otherUser->broadband_account_number,
            'admin_id' => null,
        ]);
        $broadbandApplication = InstallationApplication::factory()->create([
            'user_id' => $otherUser->id,
            'admin_id' => null,
        ]);

        $this->withToken($token)
            ->getJson('/api/failure-reports/'.$failureReport->id)
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/change-plan-requests/'.$changePlanRequest->id)
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/change-password-requests/'.$changePasswordRequest->id)
            ->assertForbidden();
        $this->withToken($token)
            ->putJson('/api/relocation-requests/'.$relocationRequest->id, [
                'broadband_account_number' => $user->broadband_account_number,
                'current_address' => 'Current address',
                'new_address' => 'Updated address',
                'preferred_date' => now()->addDays(5)->toDateString(),
                'phone' => '95912345678',
            ])
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/installation-application/'.$broadbandApplication->id)
            ->assertForbidden();
    }
}
