<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * One customer = one phone spelling (digits only, no "+"), and the OTP endpoint only
 * accepts the countries the admin customer form accepts.
 */
class PhoneCanonicalFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
    }

    // ---------------------------------------------------------------------
    // Country allowlist on the OTP endpoint
    // ---------------------------------------------------------------------

    #[DataProvider('supportedNumbers')]
    public function test_otp_is_sent_to_supported_countries(string $phone): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => $phone])->assertAccepted();
    }

    public static function supportedNumbers(): array
    {
        return [
            'myanmar with plus' => ['+959123456789'],
            'myanmar copied international number' => ['+959123456789'],
            'myanmar local' => ['09123456789'],
            'myanmar bare' => ['959123456789'],
            'thailand' => ['+66812345678'],
            'thailand local' => ['0812345678'],
            'china' => ['+8613812345678'],
        ];
    }

    #[DataProvider('unsupportedNumbers')]
    public function test_otp_is_refused_for_unsupported_countries(string $phone): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => $phone])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('security_logs', 0);
    }

    public static function unsupportedNumbers(): array
    {
        return [
            'united kingdom' => ['+447911123456'],
            'united states' => ['+12025550123'],
            'premium looking' => ['+88212345678'],
            'myanmar too short' => ['+9591234'],
            'myanmar invalid mobile prefix' => ['+95912345678'],
            'thailand too long' => ['+6681234567890'],
            'thailand invalid mobile prefix' => ['+66712345678'],
            'china too short' => ['+86138123'],
            'china invalid mobile prefix' => ['+8612312345678'],
            'letters' => ['not-a-phone'],
        ];
    }

    // ---------------------------------------------------------------------
    // Admin and app share one format
    // ---------------------------------------------------------------------

    #[DataProvider('adminSpellings')]
    public function test_admin_created_customers_are_stored_in_the_canonical_format(string $typed): void
    {
        $this->actingAs(Admin::factory()->create(), 'web')
            ->post('/customers', [
                'name' => 'Admin Made',
                'phone' => $typed,
                'password' => '123456',
                'password_confirmation' => '123456',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Admin Made', 'phone' => '95922223333']);
    }

    public static function adminSpellings(): array
    {
        return [
            'plus' => ['+95922223333'],
            'local' => ['0922223333'],
            'international with local trunk prefix' => ['+950922223333'],
            'bare' => ['95922223333'],
            'spaced' => ['+95 9 222 23333'],
        ];
    }

    #[DataProvider('adminCountrySpellings')]
    public function test_admin_requests_normalize_country_specific_numbers(string $typed, string $canonical): void
    {
        $this->actingAs(Admin::factory()->create(), 'web')
            ->post('/customers', [
                'name' => 'Admin Made',
                'phone' => $typed,
                'password' => '123456',
                'password_confirmation' => '123456',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Admin Made', 'phone' => $canonical]);
    }

    public static function adminCountrySpellings(): array
    {
        return [
            'Thailand local with trunk zero' => ['0812345678', '66812345678'],
            'Thailand international with trunk zero' => ['+660812345678', '66812345678'],
            'China mobile' => ['+8613812345678', '8613812345678'],
        ];
    }

    public function test_admin_form_rejects_the_same_unsupported_countries_as_otp(): void
    {
        $this->actingAs(Admin::factory()->create(), 'web')
            ->post('/customers', [
                'name' => 'Abroad',
                'phone' => '+447911123456',
                'password' => '123456',
                'password_confirmation' => '123456',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['name' => 'Abroad']);
    }

    #[DataProvider('duplicateSpellings')]
    public function test_admin_cannot_create_a_second_account_for_the_same_number_in_another_spelling(
        string $typed,
    ): void {
        User::factory()->create(['phone' => '95933334444']);

        $this->actingAs(Admin::factory()->create(), 'web')
            ->post('/customers', [
                'name' => 'Twin',
                'phone' => $typed,
                'password' => '123456',
                'password_confirmation' => '123456',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('users', 1);
    }

    public static function duplicateSpellings(): array
    {
        return [
            'plus' => ['+95933334444'],
            'local' => ['0933334444'],
            'bare' => ['95933334444'],
        ];
    }

    public function test_admin_created_customer_can_sign_in_to_the_app_with_the_same_number(): void
    {
        $this->actingAs(Admin::factory()->create(), 'web')
            ->post('/customers', [
                'name' => 'Admin Made',
                'phone' => '+95922223333',
                'password' => '123456',
                'password_confirmation' => '123456',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $challenge = $this->postJson('/api/auth/otp/request', ['phone' => '+95922223333'])->assertAccepted();
        $verify = $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge->json('challenge_id'),
            'code' => $challenge->json('debug_otp'),
        ])->assertOk();

        // Recognised as the existing account (not offered registration, no duplicate row).
        $verify->assertJsonPath('next_step', 'password');
        $this->postJson('/api/auth/login', [
            'verification_token' => $verify->json('verification_token'),
            'password' => '123456',
        ])->assertOk();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_model_strips_a_leading_plus_whatever_writes_the_phone(): void
    {
        $user = User::factory()->create(['phone' => '+95911112222']);

        $this->assertSame('95911112222', $user->phone);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '95911112222']);
    }

    public function test_admin_search_finds_customers_when_the_plus_is_typed(): void
    {
        User::factory()->create(['name' => 'Findable', 'phone' => '95944445555']);
        User::factory()->create(['name' => 'Other', 'phone' => '95966667777']);

        $this->actingAs(Admin::factory()->create(), 'web')
            ->get('/customers?search=' . urlencode('+9594444'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Findable'),
            );
    }
}
