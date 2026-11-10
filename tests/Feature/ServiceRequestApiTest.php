<?php

namespace Tests\Feature;

use App\Enums\FailureType;
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
        foreach (
            [
                '/api/failure-reports',
                '/api/change-plan-requests',
                '/api/change-password-requests',
                '/api/relocation-requests',
                '/api/installation-application',
            ]
            as $endpoint
        ) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        foreach (
            [
                '/api/failure-reports/create',
                '/api/change-plan-requests/create',
                '/api/change-password-requests/create',
                '/api/relocation-requests/create',
                '/api/installation-application/create',
            ]
            as $endpoint
        ) {
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
        $applicationData = [
            'name' => 'Test Customer',
            'service_type' => 'FTTH',
            'package_detail' => [
                'product_id' => '249',
                'product_name' => '100M office',
                'product_line' => 'FTTH',
                'product_speed' => '100M',
                'product_price' => 2000,
                'installation_fee' => 500,
                'month_cycle' => 1,
                'month_cycle_unit' => '月',
            ],
            'address' => 'Customer address',
            'phone' => '09922345678',
            'note' => 'Please install soon.',
            'id_type' => 'NRC',
            'id_name' => 'Test Customer',
            'id_number' => '12/ABC(N)123456',
            'photos' => [UploadedFile::fake()->image('id-front.jpg'), UploadedFile::fake()->image('id-back.jpg')],
        ];

        $response = $this->withToken($token)
            ->post('/api/installation-application/create', $applicationData, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('code', 'MSG-REQ-002')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.name', 'Test Customer')
            ->assertJsonPath('data.service_type', 'FTTH')
            ->assertJsonPath('data.ticket_number', 'INT' . now()->format('md') . '0001')
            ->assertJsonPath('data.package_detail.product_id', '249')
            ->assertJsonPath('data.phone', '959922345678')
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonMissingPath('data.photos');
        Http::assertSentCount(1);
        Http::assertSent(fn($request) => str_contains($request['text'], '📡 NEW BROADBAND INSTALLATION'));
        $application = InstallationApplication::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)->getJson('/api/installation-application')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/installation-application/' . $application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id);

        $this->withToken($token)
            ->put(
                '/api/installation-application/' . $application->id,
                [
                    ...$applicationData,
                    'name' => 'Updated Customer',
                    'address' => 'Updated customer address',
                    'photos' => [
                        UploadedFile::fake()->image('updated-front.jpg'),
                        UploadedFile::fake()->image('updated-back.jpg'),
                    ],
                ],
                ['Accept' => 'application/json'],
            )
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-003')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.name', 'Updated Customer')
            ->assertJsonPath('data.address', 'Updated customer address')
            ->assertJsonMissingPath('data.photos');
        Http::assertSentCount(2);
        Http::assertSent(fn($request) => str_contains($request['text'], '🔄 BROADBAND INSTALLATION UPDATED'));

        $this->withToken($token)
            ->patchJson('/api/installation-application/' . $application->id . '/cancel')
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-001')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.status', 'cancelled');
        Http::assertSentCount(3);
        Http::assertSent(fn($request) => str_contains($request['text'], '🚫 BROADBAND INSTALLATION CANCELLED'));

        $this->withToken($token)
            ->deleteJson('/api/installation-application/' . $application->id)
            ->assertNoContent();

        $this->assertSoftDeleted('installation_applications', ['id' => $application->id]);
    }

    public function test_installation_products_come_from_walletapp_ftth_and_cg_commands(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'provider-client',
            'services.cg_api.client_secret' => 'provider-secret',
        ]);
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/FTTHProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [
                    [
                        'productID' => '249',
                        'productName' => '办公移动网_100M_月',
                        'productPrice' => 2000.0,
                        'monthCycle' => 1,
                        'monthCycleUnit' => '月',
                        'productLine' => '移动网_办公',
                        'productSpeed' => '100M',
                        'installationFee' => '500',
                    ],
                ],
            ]),
            'provider.test/WalletApp/ServerCommand/CGRouterProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [
                    [
                        'productID' => '310',
                        'productName' => 'CG Router 50M',
                        'productPrice' => 1500.0,
                        'monthCycle' => 1,
                        'monthCycleUnit' => '月',
                        'productLine' => 'CG-Router',
                        'productSpeed' => '50M',
                        'installationFee' => 0,
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('service-requests')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/installation-application/products?service_type=FTTH')
            ->assertOk()
            ->assertJsonPath('data.0.productID', '249')
            ->assertJsonPath('data.0.productLine', '移动网_办公');

        $this->withToken($token)
            ->getJson('/api/installation-application/products?service_type=CG')
            ->assertOk()
            ->assertJsonPath('data.0.productID', '310');
    }

    public function test_installation_products_endpoint_returns_message_code_when_catalog_unavailable(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'provider-client',
            'services.cg_api.client_secret' => 'provider-secret',
        ]);
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/FTTHProductInfoListAPI' => Http::response([
                'ErrCode' => 100,
                'Message' => 'provider boom',
                'result' => [],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('service-requests')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/installation-application/products?service_type=FTTH')
            ->assertStatus(503)
            ->assertJsonPath('code', 'MSG-PKG-003')
            ->assertJsonMissing(['message' => 'provider boom']);
    }

    public function test_customer_can_create_update_cancel_and_delete_a_relocation_request(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create(['broadband_account_number' => 'CG62345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $requestData = [
            'broadband_account_number' => $user->broadband_account_number,
            'contact_name' => 'Test Customer',
            'current_address' => 'Current address',
            'new_address' => 'New address',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'phone' => '95912345678',
            'details' => 'Please call before the relocation.',
        ];

        $response = $this->withToken($token)
            ->postJson('/api/relocation-requests/create', $requestData)
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.broadband_account_number', $user->broadband_account_number)
            ->assertJsonPath('data.status', 'under_review');
        Http::assertSentCount(1);
        $relocationRequest = RelocationRequest::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)->getJson('/api/relocation-requests')->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->putJson('/api/relocation-requests/' . $relocationRequest->id, [
                ...$requestData,
                'new_address' => 'Updated new address',
                'details' => 'Updated relocation details.',
            ])
            ->assertOk()
            ->assertJsonPath('data.new_address', 'Updated new address')
            ->assertJsonPath('data.details', 'Updated relocation details.');
        Http::assertSentCount(2);
        Http::assertSent(fn($request) => str_contains($request['text'], '🔄 RELOCATION REQUEST UPDATED'));

        $this->withToken($token)
            ->patchJson('/api/relocation-requests/' . $relocationRequest->id . '/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        Http::assertSentCount(3);
        Http::assertSent(fn($request) => str_contains($request['text'], '🚫 RELOCATION REQUEST CANCELLED'));

        $this->withToken($token)
            ->deleteJson('/api/relocation-requests/' . $relocationRequest->id)
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
            ->assertJsonPath('code', 'MSG-REQ-002')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.broadband_account_number', $user->broadband_account_number)
            ->assertJsonPath('data.ticket_number', 'FLR' . now()->format('md') . '0001')
            ->assertJsonPath('data.failure_type', FailureType::NoInternet->value)
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonCount(3, 'data.photos');

        $failureReport = FailureReport::query()->findOrFail($response->json('data.id'));
        $this->assertDatabaseCount('failure_photos', 3);

        $this->withToken($token)->getJson('/api/failure-reports')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/failure-reports/' . $failureReport->id)
            ->assertOk()
            ->assertJsonPath('data.id', $failureReport->id);

        $this->withToken($token)
            ->putJson('/api/failure-reports/' . $failureReport->id, [
                ...$createData,
                'photos' => null,
                'failure_type' => FailureType::Slow->value,
                'description' => 'The connection is slow.',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-003')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.failure_type', FailureType::Slow->value)
            ->assertJsonPath('data.description', 'The connection is slow.');

        $photos = $failureReport->photos()->get();
        $photoPaths = $photos->pluck('image_url')->all();

        $this->withToken($token)
            ->patchJson('/api/failure-reports/' . $failureReport->id . '/cancel')
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-001')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonCount(3, 'data.photos');

        foreach ($photoPaths as $photoPath) {
            $this->assertTrue(Storage::disk('public')->exists($photoPath));
        }
        foreach ($photos as $photo) {
            $this->assertDatabaseHas('failure_photos', [
                'id' => $photo->id,
                'image_url' => $photo->image_url,
            ]);
        }

        $this->withToken($token)
            ->deleteJson('/api/failure-reports/' . $failureReport->id)
            ->assertNoContent();

        $this->assertSoftDeleted('failure_reports', ['id' => $failureReport->id]);
    }

    public function test_customer_can_create_a_failure_report_without_photos(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        $token = $user->createToken('service-requests')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/failure-reports/create', [
                'broadband_account_number' => $user->broadband_account_number,
                'failure_type' => FailureType::NoInternet->value,
                'description' => 'The connection has been unavailable since this morning.',
                'contact_name' => 'Test Customer',
                'contact_phone' => '95912345678',
                'photos' => null,
            ])
            ->assertCreated()
            ->assertJsonCount(0, 'data.photos');

        $this->assertDatabaseCount('failure_photos', 0);
    }

    public function test_updating_failure_report_photos_removes_unsubmitted_photos(): void
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
            ->assertCreated();

        $failureReport = FailureReport::query()->findOrFail($response->json('data.id'));
        $photos = $failureReport->photos()->get();
        $photoPaths = $photos->pluck('image_url')->all();

        $this->withToken($token)
            ->post(
                '/api/failure-reports/' . $failureReport->id,
                [
                    '_method' => 'PUT',
                    ...$createData,
                    'photos' => [UploadedFile::fake()->image('replacement.jpg')],
                ],
                ['Accept' => 'application/json'],
            )
            ->assertOk()
            ->assertJsonCount(1, 'data.photos');

        foreach ($photos->slice(1) as $photo) {
            $this->assertSoftDeleted('failure_photos', ['id' => $photo->id]);
        }
        foreach ($photoPaths as $photoPath) {
            $this->assertFalse(Storage::disk('public')->exists($photoPath));
        }
        $this->assertDatabaseCount('failure_photos', 3);
        $this->assertCount(1, $failureReport->photos()->get());
    }

    public function test_customer_must_submit_at_least_one_photo_when_photos_are_provided(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $requestData = [
            'broadband_account_number' => $user->broadband_account_number,
            'failure_type' => FailureType::NoInternet->value,
            'description' => 'The connection has been unavailable since this morning.',
            'contact_name' => 'Test Customer',
            'contact_phone' => '95912345678',
        ];

        $this->withToken($token)
            ->postJson('/api/failure-reports/create', [...$requestData, 'photos' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos');

        $this->withToken($token)
            ->post(
                '/api/failure-reports/create',
                [...$requestData, 'photos' => [UploadedFile::fake()->image('router.jpg')]],
                ['Accept' => 'application/json'],
            )
            ->assertCreated()
            ->assertJsonCount(1, 'data.photos');

        $this->assertDatabaseCount('failure_photos', 1);
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
            'service_type' => 'CG',
            'current_package_detail' => $currentPackage->changePlanDetail(),
            'new_package_detail' => $newPackage->changePlanDetail(),
            'preferred_date' => now()->addDays(3)->toDateString(),
            'contact_name' => 'Test Customer',
            'contact_phone' => '95912345678',
            'note' => 'Please change my package.',
        ];

        $response = $this->withToken($token)
            ->postJson('/api/change-plan-requests/create', $requestData)
            ->assertCreated()
            ->assertJsonPath('code', 'MSG-REQ-002')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.service_type', 'CG');
        $this->assertNotEmpty($response->json('data.ticket_number'));
        $this->assertStringStartsWith('CPL', (string) $response->json('data.ticket_number'));
        $changePlanRequest = ChangePlanRequest::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)->getJson('/api/change-plan-requests')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/change-plan-requests/' . $changePlanRequest->id)
            ->assertOk()
            ->assertJsonPath('data.id', $changePlanRequest->id);

        $this->withToken($token)
            ->putJson('/api/change-plan-requests/' . $changePlanRequest->id, [
                ...$requestData,
                'new_package_detail' => Package::factory()->create()->changePlanDetail(),
                'note' => 'Please apply the updated package.',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-003')
            ->assertJsonPath('data.note', 'Please apply the updated package.');

        $this->withToken($token)
            ->deleteJson('/api/change-plan-requests/' . $changePlanRequest->id)
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-001');

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

        $response = $this->withToken($token)
            ->postJson('/api/change-password-requests/create', $requestData)
            ->assertCreated()
            ->assertJsonPath('code', 'MSG-REQ-002')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.ticket_number', 'CWP' . now()->format('md') . '0001')
            ->assertJsonPath('data.status', 'under_review');
        $changePasswordRequest = ChangePasswordRequest::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)->getJson('/api/change-password-requests')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)
            ->getJson('/api/change-password-requests/' . $changePasswordRequest->id)
            ->assertOk()
            ->assertJsonPath('data.id', $changePasswordRequest->id)
            ->assertJsonPath('data.ticket_number', $changePasswordRequest->ticket_number);

        $this->withToken($token)
            ->putJson('/api/change-password-requests/' . $changePasswordRequest->id, [
                ...$requestData,
                'new_wifi_name' => 'NewWifi',
                'new_password' => 'another-secret',
                'status' => 'approved',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->assertSame('under_review', $changePasswordRequest->refresh()->status->value);

        $this->withToken($token)
            ->putJson('/api/change-password-requests/' . $changePasswordRequest->id, [
                ...$requestData,
                'new_wifi_name' => 'NewWifi',
                'new_password' => 'another-secret',
                'status' => 'under_review',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-003')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.new_wifi_name', 'NewWifi')
            ->assertJsonPath('data.status', 'under_review');

        $this->withToken($token)
            ->patchJson('/api/change-password-requests/' . $changePasswordRequest->id . '/cancel')
            ->assertOk()
            ->assertJsonPath('code', 'MSG-REQ-001')
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('data.status', 'cancelled');

        $this->withToken($token)
            ->patchJson('/api/change-password-requests/' . $changePasswordRequest->id . '/cancel')
            ->assertConflict();

        $this->withToken($token)
            ->deleteJson('/api/change-password-requests/' . $changePasswordRequest->id)
            ->assertNoContent();

        $this->assertSoftDeleted('change_password_requests', ['id' => $changePasswordRequest->id]);
    }

    public function test_change_password_requests_reject_passwords_over_30_characters(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG32345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $requestData = [
            'user_id' => $user->id,
            'broadband_account_number' => $user->broadband_account_number,
            'new_wifi_name' => 'UpdatedWifi',
            'new_password' => str_repeat('x', 31),
            'contact_name' => 'Test Customer',
            'contact_phone' => '95912345678',
        ];

        $this->withToken($token)
            ->postJson('/api/change-password-requests/create', $requestData)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_password');

        $response = $this->withToken($token)
            ->postJson('/api/change-password-requests/create', [...$requestData, 'new_password' => str_repeat('x', 30)])
            ->assertCreated();
        $changePasswordRequest = ChangePasswordRequest::query()->findOrFail($response->json('data.id'));

        $this->withToken($token)
            ->putJson('/api/change-password-requests/' . $changePasswordRequest->id, [
                ...$requestData,
                'new_password' => str_repeat('x', 31),
                'status' => 'under_review',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_password');

        $this->assertSame(str_repeat('x', 30), $changePasswordRequest->refresh()->new_password);
    }

    public function test_ftth_customers_can_create_and_update_change_password_requests(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'FTTH12345678']);
        $token = $user->createToken('service-requests')->plainTextToken;
        $requestData = [
            'user_id' => $user->id,
            'broadband_account_number' => $user->broadband_account_number,
            'new_wifi_name' => 'UpdatedWifi',
            'new_password' => 'updated-secret',
            'contact_name' => 'Test Customer',
            'contact_phone' => '95912345678',
        ];

        $this->withToken($token)
            ->postJson('/api/change-password-requests/create', $requestData)
            ->assertCreated()
            ->assertJsonPath('data.broadband_account_number', $user->broadband_account_number);
        $this->assertDatabaseCount('change_password_requests', 1);

        $changePasswordRequest = ChangePasswordRequest::factory()->create([
            'user_id' => $user->id,
            'broadband_account_number' => $user->broadband_account_number,
        ]);
        $this->withToken($token)
            ->putJson('/api/change-password-requests/' . $changePasswordRequest->id, [
                ...$requestData,
                'status' => 'under_review',
            ])
            ->assertOk()
            ->assertJsonPath('data.broadband_account_number', $user->broadband_account_number)
            ->assertJsonPath('data.status', 'under_review');
        $this->assertSame('under_review', $changePasswordRequest->refresh()->status->value);
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
            ->getJson('/api/failure-reports/' . $failureReport->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'MSG-SYS-003')
            ->assertJsonPath('level', 'error');
        $this->withToken($token)
            ->getJson('/api/change-plan-requests/' . $changePlanRequest->id)
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/change-password-requests/' . $changePasswordRequest->id)
            ->assertForbidden();
        $this->withToken($token)
            ->putJson('/api/relocation-requests/' . $relocationRequest->id, [
                'broadband_account_number' => $user->broadband_account_number,
                'contact_name' => 'Test Customer',
                'current_address' => 'Current address',
                'new_address' => 'Updated address',
                'preferred_date' => now()->addDays(5)->toDateString(),
                'phone' => '95912345678',
            ])
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/installation-application/' . $broadbandApplication->id)
            ->assertForbidden();
    }

    public function test_installation_update_ignores_photo_metadata_and_keeps_the_stored_photos(): void
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
        $packageDetail = [
            'product_id' => '249',
            'product_name' => '100M office',
            'product_line' => 'FTTH',
            'product_speed' => '100M',
            'product_price' => 2000,
            'installation_fee' => 500,
            'month_cycle' => 1,
            'month_cycle_unit' => '月',
        ];
        $created = $this->withToken($token)
            ->post(
                '/api/installation-application/create',
                [
                    'name' => 'Test Customer',
                    'service_type' => 'FTTH',
                    'package_detail' => $packageDetail,
                    'address' => 'Customer address',
                    'phone' => '95922345678',
                    'note' => 'Please install soon.',
                ],
                ['Accept' => 'application/json'],
            )
            ->assertCreated();

        $this->withToken($token)
            ->putJson('/api/installation-application/' . $created->json('data.id'), [
                'name' => 'Edited Without New Photos',
                'service_type' => 'FTTH',
                'package_detail' => $packageDetail,
                'address' => 'Customer address',
                'phone' => '95922345678',
                'note' => 'Please install soon.',
                'status' => 'under_review',
                'photos' => [
                    ['image_url' => 'https://example.test/front.jpg'],
                    ['image_url' => 'https://example.test/back.jpg'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Edited Without New Photos')
            ->assertJsonMissingPath('data.photos');
    }

    public function test_installation_application_rejects_an_unsupported_phone(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('service-requests')->plainTextToken;
        $this->withToken($token)
            ->postJson('/api/installation-application/create', [
                'name' => 'Test Customer',
                'service_type' => 'FTTH',
                'package_detail' => [
                    'product_id' => '249',
                    'product_name' => '100M office',
                    'product_price' => 2000,
                    'installation_fee' => 500,
                    'month_cycle' => 1,
                ],
                'address' => 'Customer address',
                'phone' => 'abc',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('installation_applications', 0);
    }

    public function test_failure_report_create_rejects_an_approved_status(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['broadband_account_number' => 'CG62345679']);
        $token = $user->createToken('service-requests')->plainTextToken;

        $this->withToken($token)
            ->post(
                '/api/failure-reports/create',
                [
                    'broadband_account_number' => $user->broadband_account_number,
                    'failure_type' => FailureType::NoInternet->value,
                    'description' => 'The connection has been unavailable since this morning.',
                    'contact_name' => 'Test Customer',
                    'contact_phone' => '95912345678',
                    'status' => 'approved',
                    'photos' => [
                        UploadedFile::fake()->image('router-1.jpg'),
                        UploadedFile::fake()->image('router-2.jpg'),
                        UploadedFile::fake()->image('router-3.jpg'),
                    ],
                ],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('failure_reports', 0);
    }

    public function test_change_password_rejects_a_wifi_name_longer_than_the_column(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG72345678']);
        $token = $user->createToken('service-requests')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/change-password-requests/create', [
                'user_id' => $user->id,
                'broadband_account_number' => $user->broadband_account_number,
                'new_wifi_name' => str_repeat('a', 256),
                'contact_name' => 'Test Customer',
                'contact_phone' => '95912345678',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_wifi_name');

        $this->assertDatabaseCount('change_password_requests', 0);
    }
}
