<?php

namespace Tests\Feature;

use App\Mail\AdminVerificationCodeMail;
use App\Models\AdminVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class AdminAccountSecurityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    public function test_owner_is_created_by_command_and_activated_only_after_email_verification(): void
    {
        Mail::fake();
        $business = $this->createBusiness(['slug' => 'owner-company']);

        $this->artisan('app:create-owner', [
            '--business' => $business->slug,
            '--name' => 'Tulajdonos Anna',
            '--email' => 'owner@example.test',
        ])->assertExitCode(0);

        $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('owner', $owner->role);
        $this->assertNull($owner->email_verified_at);

        $code = null;
        Mail::assertQueued(AdminVerificationCodeMail::class, function ($mail) use (&$code): bool {
            if ($mail->purpose !== AdminVerificationCode::PURPOSE_OWNER_ACTIVATION
                || ! $mail->hasTo('owner@example.test')) {
                return false;
            }
            $code = $mail->code;
            return true;
        });
        $this->assertNotNull($code);

        foreach ([1, 2] as $offset) {
            $wrongCode = str_pad((string) (((int) $code + $offset) % 1000000), 6, '0', STR_PAD_LEFT);
            $this->postJson('/api/v1/auth/owner/activate', [
                'email' => 'owner@example.test',
                'code' => $wrongCode,
                'password' => 'NewOwnerPassword123!',
                'password_confirmation' => 'NewOwnerPassword123!',
            ])->assertUnprocessable()
                ->assertJsonPath('errors.code.0', 'A megadott kód hibás. Még '.(5 - $offset).' próbálkozásod maradt ezzel a kóddal.');
        }

        $this->assertDatabaseHas('admin_verification_codes', [
            'user_id' => $owner->id,
            'purpose' => AdminVerificationCode::PURPOSE_OWNER_ACTIVATION,
            'attempts' => 2,
        ]);

        $this->postJson('/api/v1/auth/owner/activate', [
            'email' => 'owner@example.test',
            'code' => $code,
            'password' => 'NewOwnerPassword123!',
            'password_confirmation' => 'NewOwnerPassword123!',
        ])->assertOk()
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonPath('user.is_owner', true);

        $owner->refresh();
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(Hash::check('NewOwnerPassword123!', $owner->password));
        $this->assertDatabaseMissing('admin_verification_codes', ['user_id' => $owner->id]);

        $this->artisan('app:create-owner', [
            '--business' => $business->slug,
            '--name' => 'Másik Tulajdonos',
            '--email' => 'other-owner@example.test',
        ])->assertExitCode(1);

        $legacyAdmin = $this->createAdmin($business, ['email' => 'legacy-admin@example.test']);
        $legacyToken = $legacyAdmin->createToken('admin', ['admin'])->plainTextToken;
        $this->artisan('app:remove-admin', [
            '--business' => $business->slug,
            '--email' => $legacyAdmin->email,
            '--force' => true,
        ])->assertExitCode(0);
        $this->assertDatabaseMissing('users', ['id' => $legacyAdmin->id]);
        $this->assertNull(PersonalAccessToken::findToken($legacyToken));
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner']);
    }

    public function test_admin_profile_email_password_and_sessions_are_managed_securely(): void
    {
        Mail::fake();
        $business = $this->createBusiness();
        $user = $this->createAdmin($business, ['email' => 'security@example.test']);

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword123!',
        ])->assertOk()->json('token');
        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword123!',
        ])->assertOk()->json('token');

        $sessions = $this->withToken($second)->getJson('/api/v1/auth/sessions')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->assertCount(1, collect($sessions->json('data'))->where('current', true));
        $otherSessionId = collect($sessions->json('data'))->firstWhere('current', false)['id'];
        $this->withToken($second)->deleteJson("/api/v1/auth/sessions/{$otherSessionId}")
            ->assertOk()->assertJsonPath('current', false);
        $this->assertNull(PersonalAccessToken::findToken($first));

        $this->withToken($second)->patchJson('/api/v1/auth/profile', [
            'name' => 'Új Admin Név',
        ])->assertOk()->assertJsonPath('user.name', 'Új Admin Név');

        $third = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword123!',
        ])->assertOk()->json('token');

        $this->withToken($second)->postJson('/api/v1/auth/email/change', [
            'email' => 'new-security@example.test',
            'current_password' => 'CorrectPassword123!',
        ])->assertStatus(202)->assertJsonPath('pending_email', 'new-security@example.test');

        $emailCode = null;
        Mail::assertQueued(AdminVerificationCodeMail::class, function ($mail) use (&$emailCode): bool {
            if ($mail->purpose !== AdminVerificationCode::PURPOSE_EMAIL_CHANGE
                || ! $mail->hasTo('new-security@example.test')) {
                return false;
            }
            $emailCode = $mail->code;
            return true;
        });

        $this->withToken($second)->postJson('/api/v1/auth/email/verify', [
            'code' => $emailCode,
        ])->assertOk()->assertJsonPath('user.email', 'new-security@example.test');
        $this->assertNull(PersonalAccessToken::findToken($third));
        $this->assertNotNull(PersonalAccessToken::findToken($second));

        $this->withToken($second)->patchJson('/api/v1/auth/password', [
            'current_password' => 'CorrectPassword123!',
            'password' => 'ChangedAdminPassword123!',
            'password_confirmation' => 'ChangedAdminPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('ChangedAdminPassword123!', $user->fresh()->password));
        $this->withToken($second)->postJson('/api/v1/auth/logout-all')->assertOk();
        $this->assertNull(PersonalAccessToken::findToken($second));
    }

    public function test_forgotten_admin_password_uses_a_reusable_until_success_code_and_revokes_old_sessions(): void
    {
        Mail::fake();
        $business = $this->createBusiness();
        $user = $this->createAdmin($business, ['email' => 'forgot-admin@example.test']);
        $oldToken = $user->createToken('admin', ['admin'], now()->addDay())->plainTextToken;

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(202);

        $code = null;
        Mail::assertQueued(AdminVerificationCodeMail::class, function ($mail) use (&$code): bool {
            if ($mail->purpose !== AdminVerificationCode::PURPOSE_PASSWORD_RESET) return false;
            $code = $mail->code;
            return true;
        });

        $wrongCode = $code === '111111' ? '111112' : '111111';
        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => $wrongCode,
            'password' => 'ResetAdminPassword123!',
            'password_confirmation' => 'ResetAdminPassword123!',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'A megadott kód hibás. Még 4 próbálkozásod maradt ezzel a kóddal.');

        $newToken = $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'ResetAdminPassword123!',
            'password_confirmation' => 'ResetAdminPassword123!',
        ])->assertOk()->json('token');

        $this->assertNull(PersonalAccessToken::findToken($oldToken));
        $this->assertNotNull(PersonalAccessToken::findToken($newToken));
        $this->assertTrue(Hash::check('ResetAdminPassword123!', $user->fresh()->password));
    }
}
