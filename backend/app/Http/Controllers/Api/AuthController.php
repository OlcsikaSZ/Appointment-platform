<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminSecurityNotificationMail;
use App\Models\AdminVerificationCode;
use App\Models\Business;
use App\Models\User;
use App\Rules\PersonName;
use App\Services\AdminVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $email = mb_strtolower(trim($credentials['email']));
        $user = User::query()->with('business')->where('email', $email)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Hibás e-mail-cím vagy jelszó.']]);
        }

        $this->assertAdminUser($user);
        if (! $user->email_verified_at) {
            return response()->json([
                'message' => 'Az owner fiók e-mail-címe még nincs megerősítve. Add meg az aktiválókódot.',
                'code' => 'ADMIN_EMAIL_UNVERIFIED',
                'email' => $user->email,
            ], 403);
        }

        return response()->json($this->issueToken($user, $request));
    }

    public function resendOwnerActivation(Request $request, AdminVerificationService $verification): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:160']]);
        $email = mb_strtolower(trim($validated['email']));
        $user = User::query()->with('business')
            ->where('email', $email)->where('role', 'owner')->whereNull('email_verified_at')->first();

        if ($user?->business?->active) {
            $verification->issue($user, AdminVerificationCode::PURPOSE_OWNER_ACTIVATION, $email);
        }

        return response()->json([
            'message' => 'Ha ehhez a címhez aktiválásra váró owner fiók tartozik, elküldtük az új kódot.',
        ], 202);
    }

    public function activateOwner(Request $request, AdminVerificationService $verification): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', $this->adminPasswordRule()],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $user = User::query()->with('business')
            ->where('email', $email)->where('role', 'owner')->whereNull('email_verified_at')->first();

        if (! $user) {
            throw ValidationException::withMessages(['code' => ['A kód lejárt vagy nem létezik. Kérj új kódot.']]);
        }

        $this->assertAdminUser($user);
        $verification->validate($user, AdminVerificationCode::PURPOSE_OWNER_ACTIVATION, $email, $validated['code']);

        DB::transaction(function () use ($user, $validated, $verification): void {
            $user->update([
                'password' => $validated['password'],
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]);
            $verification->forget($user, AdminVerificationCode::PURPOSE_OWNER_ACTIVATION);
        });

        $this->notifySecurity($user->business, $user->email, 'Tulajdonosi fiók aktiválva', [
            'A tulajdonosi fiók e-mail-ellenőrzése és jelszóbeállítása sikeresen megtörtént.',
        ]);

        return response()->json($this->issueToken($user->fresh('business'), $request));
    }

    public function forgotPassword(Request $request, AdminVerificationService $verification): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:160']]);
        $email = mb_strtolower(trim($validated['email']));
        $user = User::query()->with('business')->where('email', $email)
            ->whereIn('role', ['admin', 'owner'])->whereNotNull('email_verified_at')->first();

        if ($user?->business?->active) {
            $verification->issue($user, AdminVerificationCode::PURPOSE_PASSWORD_RESET, $email);
        }

        return response()->json([
            'message' => 'Ha ehhez az e-mail-címhez aktív adminfiók tartozik, elküldtük a jelszó-visszaállító kódot.',
        ], 202);
    }

    public function resetPassword(Request $request, AdminVerificationService $verification): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', $this->adminPasswordRule()],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $user = User::query()->with('business')->where('email', $email)
            ->whereIn('role', ['admin', 'owner'])->whereNotNull('email_verified_at')->first();

        if (! $user) {
            throw ValidationException::withMessages(['code' => ['A kód lejárt vagy nem létezik. Kérj új kódot.']]);
        }

        $this->assertAdminUser($user);
        $verification->validate($user, AdminVerificationCode::PURPOSE_PASSWORD_RESET, $email, $validated['code']);

        DB::transaction(function () use ($user, $validated, $verification): void {
            $user->update(['password' => $validated['password'], 'password_changed_at' => now()]);
            $user->tokens()->where('name', 'admin')->delete();
            $verification->forget($user, AdminVerificationCode::PURPOSE_PASSWORD_RESET);
        });

        $this->notifySecurity($user->business, $user->email, 'Admin jelszó visszaállítva', [
            'A jelszavad ellenőrző kóddal megváltozott. Minden korábbi admin munkamenetet kijelentkeztettünk.',
        ]);

        return response()->json($this->issueToken($user->fresh('business'), $request));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('business');
        $this->assertAdminUser($user);

        return response()->json([
            'user' => $this->userPayload($user),
            'idle_timeout_minutes' => (int) config('appointment.admin_idle_timeout_minutes', 4320),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('business');
        $validated = $request->validate(['name' => ['required', 'string', new PersonName()]]);
        $oldName = $user->name;
        $user->update(['name' => trim($validated['name'])]);

        if ($oldName !== $user->name) {
            $this->notifySecurity($user->business, $user->email, 'Adminprofil neve módosult', [
                "Korábbi név: {$oldName}", "Új név: {$user->name}",
            ]);
        }

        return response()->json(['user' => $this->userPayload($user->fresh('business'))]);
    }

    public function requestEmailChange(Request $request, AdminVerificationService $verification): JsonResponse
    {
        $user = $request->user()->loadMissing('business');
        $validated = $request->validate([
            'email' => [
                'required', 'email:rfc', 'max:160',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('users', 'pending_email')->ignore($user->id),
            ],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['A jelenlegi jelszó hibás.']]);
        }

        $newEmail = mb_strtolower(trim($validated['email']));
        if ($newEmail === $user->email) {
            throw ValidationException::withMessages(['email' => ['Ez már a jelenlegi e-mail-címed.']]);
        }

        $user->update(['pending_email' => $newEmail]);
        $minutes = $verification->issue($user, AdminVerificationCode::PURPOSE_EMAIL_CHANGE, $newEmail);
        $this->notifySecurity($user->business, $user->email, 'Admin e-mail-cím módosítása elindítva', [
            "Az ellenőrzésre váró új cím: {$newEmail}",
            'A régi cím addig marad aktív, amíg az új címet nem erősíted meg.',
        ]);

        return response()->json([
            'message' => 'Az ellenőrző kódot elküldtük az új e-mail-címre.',
            'pending_email' => $newEmail,
            'expires_in_minutes' => $minutes,
            'user' => $this->userPayload($user),
        ], 202);
    }

    public function verifyEmailChange(Request $request, AdminVerificationService $verification): JsonResponse
    {
        $user = $request->user()->loadMissing('business');
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        if (! $user->pending_email) {
            throw ValidationException::withMessages(['code' => ['Nincs megerősítésre váró új e-mail-cím.']]);
        }

        $verification->validate(
            $user, AdminVerificationCode::PURPOSE_EMAIL_CHANGE, $user->pending_email, $validated['code'],
        );
        $oldEmail = $user->email;
        $newEmail = $user->pending_email;
        $currentTokenId = $user->currentAccessToken()?->id;

        DB::transaction(function () use ($user, $newEmail, $currentTokenId, $verification): void {
            $user->update(['email' => $newEmail, 'pending_email' => null, 'email_verified_at' => now()]);
            $user->tokens()->where('name', 'admin')
                ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();
            $verification->forget($user, AdminVerificationCode::PURPOSE_EMAIL_CHANGE);
        });

        $lines = [
            "Korábbi cím: {$oldEmail}", "Új cím: {$newEmail}",
            'A többi admin munkamenetet biztonsági okból kijelentkeztettük.',
        ];
        $this->notifySecurity($user->business, $oldEmail, 'Admin e-mail-cím megváltozott', $lines);
        $this->notifySecurity($user->business, $newEmail, 'Admin e-mail-cím megerősítve', $lines);

        return response()->json([
            'message' => 'Az új e-mail-címet megerősítettük.',
            'user' => $this->userPayload($user->fresh('business')),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('business');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', $this->adminPasswordRule()],
        ]);
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['A jelenlegi jelszó hibás.']]);
        }

        $currentTokenId = $user->currentAccessToken()?->id;
        DB::transaction(function () use ($user, $validated, $currentTokenId): void {
            $user->update(['password' => $validated['password'], 'password_changed_at' => now()]);
            $user->tokens()->where('name', 'admin')
                ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();
        });

        $this->notifySecurity($user->business, $user->email, 'Admin jelszó megváltozott', [
            'A jelszó sikeresen frissült. A jelenlegi kivételével minden admin munkamenetet kijelentkeztettünk.',
        ]);

        return response()->json(['message' => 'A jelszó frissült, a többi munkamenetet kijelentkeztettük.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()?->id;

        return response()->json(['data' => $user->tokens()->where('name', 'admin')->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'current' => $token->id === $currentId,
            'ip_address' => $token->ip_address,
            'user_agent' => $token->user_agent,
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ])]);
    }

    public function destroySession(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user()->loadMissing('business');
        $token = $user->tokens()->where('name', 'admin')->whereKey($tokenId)->firstOrFail();
        $wasCurrent = $token->id === $user->currentAccessToken()?->id;
        $details = [
            'Böngésző: '.mb_substr((string) ($token->user_agent ?: 'ismeretlen'), 0, 180),
            'IP-cím: '.($token->ip_address ?: 'ismeretlen'),
        ];
        $token->delete();
        $this->notifySecurity($user->business, $user->email, 'Admin munkamenet visszavonva', $details);

        return response()->json(['message' => 'A munkamenetet kijelentkeztettük.', 'current' => $wasCurrent]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('business');
        $user->tokens()->where('name', 'admin')->delete();
        $this->notifySecurity($user->business, $user->email, 'Kijelentkezés minden eszközről', [
            'Minden aktív admin munkamenetet visszavontunk.',
        ]);

        return response()->json(['message' => 'Minden eszközről kijelentkeztél.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sikeres kijelentkezés.']);
    }

    private function issueToken(User $user, Request $request): array
    {
        $lifetimeMinutes = max(1, (int) config('appointment.admin_token_lifetime_minutes', 43200));
        $expiresAt = now()->addMinutes($lifetimeMinutes);
        $newToken = $user->createToken('admin', ['admin'], $expiresAt);
        $newToken->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        return [
            'token' => $newToken->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'idle_timeout_minutes' => (int) config('appointment.admin_idle_timeout_minutes', 4320),
            'user' => $this->userPayload($user),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'pending_email' => $user->pending_email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'role' => $user->role,
            'is_owner' => $user->role === 'owner',
            'business_id' => $user->business_id,
            'business' => $user->business ? [
                'id' => $user->business->id,
                'name' => $user->business->name,
                'slug' => $user->business->slug,
                'active' => $user->business->active,
                'timezone' => $user->business->timezone,
            ] : null,
        ];
    }

    private function assertAdminUser(User $user): void
    {
        abort_unless(in_array($user->role, ['admin', 'owner'], true), 403, 'Ehhez a felülethez admin jogosultság szükséges.');
        abort_unless($user->business_id && $user->business, 403, 'A felhasználóhoz nincs vállalkozás rendelve.');
        abort_unless($user->business->active, 403, 'Ez a vállalkozás jelenleg inaktív.');
    }

    private function adminPasswordRule(): Password
    {
        return Password::min(10)->mixedCase()->numbers()->symbols();
    }

    private function notifySecurity(Business $business, string $email, string $title, array $lines): void
    {
        Mail::to($email)->queue(new AdminSecurityNotificationMail($business, $title, $lines));
    }
}
