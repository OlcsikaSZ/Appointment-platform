<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    public function test_wrong_password_is_rejected(): void
    {
        $business = $this->createBusiness();
        $user = $this->createAdmin($business, ['email' => 'login@example.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_login_rate_limit_is_enforced(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77']);
        $business = $this->createBusiness();
        $user = $this->createAdmin($business, ['email' => 'rate@example.test']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_logout_revokes_current_token(): void
    {
        $business = $this->createBusiness();
        $user = $this->createAdmin($business, ['email' => 'logout@example.test']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword123!',
        ])->assertOk();

        $plainToken = $login->json('token');
        $this->assertNotNull(PersonalAccessToken::findToken($plainToken));

        $this->withToken($plainToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertNull(PersonalAccessToken::findToken($plainToken));
    }

    public function test_expired_token_is_rejected(): void
    {
        $business = $this->createBusiness();
        $user = $this->createAdmin($business);
        $token = $user->createToken('admin', ['admin'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_idle_token_is_revoked(): void
    {
        config()->set('appointment.admin_idle_timeout_minutes', 10);
        $business = $this->createBusiness();
        $user = $this->createAdmin($business);
        $plainToken = $user->createToken('admin', ['admin'], now()->addDay())->plainTextToken;
        $token = PersonalAccessToken::findToken($plainToken);
        $token->forceFill(['last_used_at' => now()->subMinutes(11)])->save();

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_IDLE_EXPIRED');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }
}
