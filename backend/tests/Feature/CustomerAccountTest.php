<?php

namespace Tests\Feature;

use App\Mail\CustomerVerificationCodeMail;
use App\Models\Booking;
use App\Models\CustomerAccount;
use App\Services\CustomerProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_registration_creates_no_account_until_email_code_is_verified(): void
    {
        Mail::fake();
        $business = $this->createBusiness(['slug' => 'password-registration']);
        $code = null;
        Mail::assertNothingQueued();

        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/register", [
            'name' => 'Kovács Anna', 'email' => 'anna@example.test', 'phone' => '+36 30 111 2222',
            'password' => 'TitkosJelszo123', 'password_confirmation' => 'TitkosJelszo123',
        ])->assertStatus(202)->assertJsonPath('email', 'anna@example.test');
        $this->assertDatabaseMissing('customer_accounts', ['email' => 'anna@example.test']);
        Mail::assertQueued(CustomerVerificationCodeMail::class, function ($mail) use (&$code): bool {
            $code = $mail->code;
            return $mail->purpose === 'registration' && preg_match('/^\d{6}$/', $code) === 1;
        });

        $wrongCode = $code === '000000' ? '000001' : '000000';
        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/verify-registration", [
            'email' => 'anna@example.test', 'code' => $wrongCode,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseHas('customer_verification_codes', [
            'email' => 'anna@example.test', 'attempts' => 1,
        ]);

        // Egy újrakérés ne érvénytelenítse a korábban kiküldött, esetleg később megérkező levelet.
        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/register", [
            'name' => 'Kovács Anna', 'email' => 'anna@example.test', 'phone' => '+36 30 111 2222',
            'password' => 'TitkosJelszo123', 'password_confirmation' => 'TitkosJelszo123',
        ])->assertStatus(202);
        $this->assertDatabaseCount('customer_verification_codes', 2);

        $response = $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/verify-registration", [
            'email' => 'anna@example.test', 'code' => $code,
        ])->assertOk()->assertJsonPath('account.role', 'user');
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('customer_accounts', ['email' => 'anna@example.test', 'role' => 'user']);
        $this->assertDatabaseMissing('customer_verification_codes', ['email' => 'anna@example.test']);
    }

    public function test_registration_code_remains_usable_when_original_expiry_passes_between_attempts(): void
    {
        Mail::fake();
        config([
            'appointment.customer_verification_code_minutes' => 15,
            'appointment.customer_verification_attempt_grace_minutes' => 5,
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 13:00:00', 'Europe/Budapest'));
        $business = $this->createBusiness(['slug' => 'registration-expiry-boundary']);
        $code = null;

        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/register", [
            'name' => 'Kovács Anna', 'email' => 'anna@example.test', 'phone' => '+36 30 111 2222',
            'password' => 'TitkosJelszo123', 'password_confirmation' => 'TitkosJelszo123',
        ])->assertStatus(202);
        Mail::assertQueued(CustomerVerificationCodeMail::class, function ($mail) use (&$code): bool {
            $code = $mail->code;
            return true;
        });

        $firstWrongCode = $code === '358700' ? '358701' : '358700';
        $secondWrongCode = $code === '358709' ? '358710' : '358709';

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 13:14:59', 'Europe/Budapest'));
        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/verify-registration", [
            'email' => 'anna@example.test', 'code' => $firstWrongCode,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'A megadott kód hibás. Még 4 próbálkozásod maradt ezzel a kóddal.');

        // Az eredeti 15 perces lejarat mar letelt, de a megkezdett
        // kodellenorzes rovid turelmi ideje meg aktiv.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 13:15:01', 'Europe/Budapest'));
        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/verify-registration", [
            'email' => 'anna@example.test', 'code' => $secondWrongCode,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'A megadott kód hibás. Még 3 próbálkozásod maradt ezzel a kóddal.');

        $response = $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/verify-registration", [
            'email' => 'anna@example.test', 'code' => $code,
        ])->assertOk()->assertJsonPath('account.email', 'anna@example.test');
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_password_login_attaches_only_matching_history_and_guest_booking_remains_available(): void
    {
        $this->freezeAppointmentTime();
        $business = $this->createBusiness(['slug' => 'optional-account']);
        $service = $this->createService($business);
        $this->addMondayHours($business);
        $ownBooking = $this->createBooking($business, $service, ['customer_contact' => 'anna@example.test']);
        $this->createBooking($business, $service, ['customer_contact' => 'bela@example.test', 'date' => '2026-08-11']);
        CustomerAccount::create([
            'business_id' => $business->id, 'name' => 'Kovács Anna', 'email' => 'anna@example.test',
            'password' => 'TitkosJelszo123', 'role' => 'user', 'email_verified_at' => now(),
        ]);

        $login = $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/login", [
            'email' => 'anna@example.test', 'password' => 'TitkosJelszo123',
        ])->assertOk()->assertJsonPath('account.email', 'anna@example.test');
        $this->withToken($login->json('token'))->getJson('/api/v1/customer/bookings')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownBooking->id);

        $this->postJson("/api/v1/businesses/{$business->slug}/bookings", $this->bookingPayload($service, [
            'time' => '11:00', 'customer_contact' => 'guest@example.test',
        ]))->assertCreated();
        $this->assertDatabaseHas('bookings', ['customer_contact' => 'guest@example.test', 'customer_account_id' => null]);
    }

    public function test_profile_password_and_session_management_work(): void
    {
        $business = $this->createBusiness();
        $account = CustomerAccount::create([
            'business_id' => $business->id, 'name' => 'Kovács Anna', 'email' => 'anna@example.test',
            'password' => 'TitkosJelszo123', 'role' => 'user', 'email_verified_at' => now(),
        ]);
        $token = $account->createToken('user-session', ['user'], now()->addDay())->plainTextToken;
        $other = $account->createToken('user-session', ['user'], now()->addDay());

        $this->withToken($token)->getJson('/api/v1/customer/sessions')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->deleteJson('/api/v1/customer/sessions/'.$other->accessToken->id)->assertOk();
        $this->withToken($token)->patchJson('/api/v1/customer/profile', ['name' => 'Kovács Anna Mária', 'phone' => '+36 20 999 0000'])
            ->assertOk()->assertJsonPath('account.phone', '+36 20 999 0000');
        $this->withToken($token)->patchJson('/api/v1/customer/password', [
            'current_password' => 'TitkosJelszo123', 'password' => 'UjJelszo456', 'password_confirmation' => 'UjJelszo456',
        ])->assertOk();
    }

    public function test_password_reset_revokes_sessions_and_requires_a_new_login(): void
    {
        Mail::fake();
        $business = $this->createBusiness(['slug' => 'password-reset-login']);
        $account = CustomerAccount::create([
            'business_id' => $business->id,
            'name' => 'Kovács Anna',
            'email' => 'anna@example.test',
            'password' => 'TitkosJelszo123',
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $account->createToken('user-session', ['user'], now()->addDay());
        $account->createToken('user-session', ['user'], now()->addDay());
        $code = null;

        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/password/forgot", [
            'email' => $account->email,
        ])->assertStatus(202);
        Mail::assertQueued(CustomerVerificationCodeMail::class, function ($mail) use (&$code): bool {
            $code = $mail->code;

            return $mail->purpose === 'password_reset';
        });

        $response = $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/password/reset", [
            'email' => $account->email,
            'code' => $code,
            'password' => 'UjTitkosJelszo456',
            'password_confirmation' => 'UjTitkosJelszo456',
        ])->assertOk()->assertJsonPath(
            'message',
            'Az új jelszó elkészült. Jelentkezz be az új jelszavaddal.',
        );

        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('customer_verification_codes', [
            'business_id' => $business->id,
            'email' => $account->email,
            'purpose' => 'password_reset',
        ]);
        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/login", [
            'email' => $account->email,
            'password' => 'TitkosJelszo123',
        ])->assertUnprocessable();
        $this->postJson("/api/v1/businesses/{$business->slug}/customer-auth/login", [
            'email' => $account->email,
            'password' => 'UjTitkosJelszo456',
        ])->assertOk()->assertJsonStructure(['token', 'expires_at', 'account']);
    }

    public function test_account_deletion_anonymizes_past_booking_but_future_booking_blocks_deletion(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 08:00:00', 'Europe/Budapest'));
        $business = $this->createBusiness(); $service = $this->createService($business);
        $account = CustomerAccount::create(['business_id' => $business->id, 'name' => 'Kovács Anna', 'email' => 'anna@example.test', 'password' => 'TitkosJelszo123', 'role' => 'user', 'email_verified_at' => now()]);
        $future = $this->createBooking($business, $service, ['customer_contact' => $account->email, 'customer_account_id' => $account->id]);
        $token = $account->createToken('user-session', ['user'], now()->addDay())->plainTextToken;
        $this->withToken($token)->deleteJson('/api/v1/customer/account')->assertStatus(409);
        $future->update(['date' => '2026-07-01', 'status' => Booking::STATUS_COMPLETED]);
        app(CustomerProfileService::class)->syncBooking($future);
        $this->withToken($token)->deleteJson('/api/v1/customer/account')->assertOk();
        $this->assertSame('Törölt ügyfél', $future->fresh()->customer_name);
    }
}
