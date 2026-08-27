<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CustomerVerificationCodeMail;
use App\Models\Business;
use App\Models\CustomerAccount;
use App\Models\CustomerVerificationCode;
use App\Rules\PersonName;
use App\Services\CustomerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->active, 404);
        $validated = $request->validate([
            'name' => ['required', 'string', new PersonName()],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $email = mb_strtolower(trim($validated['email']));

        if (CustomerAccount::query()->where('business_id', $business->id)->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Ehhez az e-mail-címhez már tartozik fiók. Jelentkezz be vagy kérj új jelszót.'],
            ]);
        }

        $code = $this->newCode();
        $validMinutes = max(5, (int) config('appointment.customer_verification_code_minutes', 15));
        $this->storeVerificationCode(
            $business,
            $email,
            CustomerVerificationCode::PURPOSE_REGISTRATION,
            $code,
            $validMinutes,
            [
                'name' => trim($validated['name']),
                'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
                'password_hash' => Hash::make($validated['password']),
            ],
        );
        Mail::to($email)->queue(new CustomerVerificationCodeMail(
            $business, $code, CustomerVerificationCode::PURPOSE_REGISTRATION, $validMinutes,
        ));

        return response()->json([
            'message' => 'Elküldtük a megerősítő kódot. A regisztráció csak a helyes kód megadása után jön létre.',
            'email' => $email,
            'expires_in_minutes' => $validMinutes,
        ], 202);
    }

    public function verifyRegistration(Request $request, Business $business, CustomerProfileService $profiles): JsonResponse
    {
        abort_unless($business->active, 404);
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $pending = $this->validCode(
            $business,
            $email,
            CustomerVerificationCode::PURPOSE_REGISTRATION,
            $validated['code'],
        );
        $account = DB::transaction(function () use ($business, $email, $profiles, $pending): CustomerAccount {
            $account = CustomerAccount::query()->create([
                'business_id' => $business->id,
                'name' => $pending->name,
                'email' => $pending->email,
                'phone' => $pending->phone,
                'password' => $pending->password_hash,
                'role' => 'user',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]);
            $profiles->attachHistoricalBookings($account);
            CustomerVerificationCode::query()
                ->where('business_id', $business->id)
                ->where('email', $email)
                ->where('purpose', CustomerVerificationCode::PURPOSE_REGISTRATION)
                ->delete();
            return $account;
        });

        return response()->json($this->issueToken($account->load('business')));
    }

    public function login(Request $request, Business $business, CustomerProfileService $profiles): JsonResponse
    {
        abort_unless($business->active, 404);
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $account = CustomerAccount::query()->with('business')
            ->where('business_id', $business->id)
            ->where('email', mb_strtolower(trim($validated['email'])))->first();
        if (! $account || ! $account->password || ! Hash::check($validated['password'], $account->password)) {
            throw ValidationException::withMessages(['email' => ['Hibás e-mail-cím vagy jelszó.']]);
        }
        abort_unless($account->email_verified_at && $account->role === 'user', 403, 'A fiók nincs megerősítve.');
        $profiles->attachHistoricalBookings($account);

        return response()->json($this->issueToken($account));
    }

    public function forgotPassword(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->active, 404);
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:160']]);
        $email = mb_strtolower(trim($validated['email']));
        $account = CustomerAccount::query()->where('business_id', $business->id)
            ->where('email', $email)->whereNotNull('email_verified_at')->first();
        if ($account) {
            $code = $this->newCode();
            $validMinutes = max(5, (int) config('appointment.customer_verification_code_minutes', 15));
            $this->storeVerificationCode(
                $business,
                $email,
                CustomerVerificationCode::PURPOSE_PASSWORD_RESET,
                $code,
                $validMinutes,
            );
            Mail::to($email)->queue(new CustomerVerificationCodeMail(
                $business, $code, CustomerVerificationCode::PURPOSE_PASSWORD_RESET, $validMinutes,
            ));
        }
        return response()->json([
            'message' => 'Ha ehhez az e-mail-címhez tartozik fiók, elküldtük a jelszó-visszaállító kódot.',
        ], 202);
    }

    public function resetPassword(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->active, 404);
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $this->validCode(
            $business,
            $email,
            CustomerVerificationCode::PURPOSE_PASSWORD_RESET,
            $validated['code'],
        );
        DB::transaction(function () use ($business, $email, $validated): void {
            $account = CustomerAccount::query()->where('business_id', $business->id)->where('email', $email)->firstOrFail();
            $account->update(['password' => $validated['password'], 'password_changed_at' => now()]);
            $account->tokens()->delete();
            CustomerVerificationCode::query()
                ->where('business_id', $business->id)
                ->where('email', $email)
                ->where('purpose', CustomerVerificationCode::PURPOSE_PASSWORD_RESET)
                ->delete();
        });

        return response()->json([
            'message' => 'Az új jelszó elkészült. Jelentkezz be az új jelszavaddal.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Sikeres kijelentkezés.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();
        return response()->json(['message' => 'Minden eszközről kijelentkeztél.']);
    }

    public function payload(CustomerAccount $account): array
    {
        return [
            'id' => $account->id, 'name' => $account->name, 'email' => $account->email,
            'phone' => $account->phone, 'role' => $account->role,
            'email_verified_at' => $account->email_verified_at?->toIso8601String(),
            'business' => [
                'id' => $account->business->id, 'name' => $account->business->name,
                'slug' => $account->business->slug,
            ],
        ];
    }

    private function issueToken(CustomerAccount $account): array
    {
        $minutes = max(1, (int) config('appointment.customer_token_lifetime_minutes', 10080));
        $expiresAt = now()->addMinutes($minutes);
        $token = $account->createToken('user-session', ['user'], $expiresAt);
        return ['token' => $token->plainTextToken, 'expires_at' => $expiresAt->toIso8601String(), 'account' => $this->payload($account)];
    }

    private function storeVerificationCode(
        Business $business,
        string $email,
        string $purpose,
        string $code,
        int $validMinutes,
        array $attributes = [],
    ): void
    {
        DB::transaction(function () use ($business, $email, $purpose, $code, $validMinutes, $attributes): void {
            $query = CustomerVerificationCode::query()
                ->where('business_id', $business->id)
                ->where('email', $email)
                ->where('purpose', $purpose);

            (clone $query)->where('expires_at', '<=', now())->delete();
            CustomerVerificationCode::query()->create(array_merge($attributes, [
                'business_id' => $business->id,
                'purpose' => $purpose,
                'email' => $email,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes($validMinutes),
            ]));

            $maxActiveCodes = max(1, (int) config('appointment.customer_verification_active_codes', 3));
            $obsoleteIds = (clone $query)->latest('id')->pluck('id')->slice($maxActiveCodes)->values();
            if ($obsoleteIds->isNotEmpty()) {
                CustomerVerificationCode::query()->whereIn('id', $obsoleteIds)->delete();
            }
        });
    }

    private function validCode(Business $business, string $email, string $purpose, string $code): CustomerVerificationCode
    {
        $result = DB::transaction(function () use ($business, $email, $purpose, $code): array {
            $maxAttempts = max(1, (int) config('appointment.customer_verification_max_attempts', 5));
            $query = CustomerVerificationCode::query()
                ->where('business_id', $business->id)
                ->where('email', $email)
                ->where('purpose', $purpose);

            (clone $query)->where('expires_at', '<=', now())->delete();
            $pendingCodes = (clone $query)->where('attempts', '<', $maxAttempts)
                ->latest('id')->lockForUpdate()->get();

            if ($pendingCodes->isEmpty()) {
                return ['pending' => null, 'message' => 'A kód lejárt vagy nem létezik. Kérj új kódot.'];
            }

            $submittedHash = hash('sha256', $code);
            foreach ($pendingCodes as $pending) {
                if (hash_equals($pending->code_hash, $submittedHash)) {
                    return ['pending' => $pending, 'message' => null];
                }
            }

            $latest = $pendingCodes->first();
            $attemptGraceMinutes = max(1, (int) config(
                'appointment.customer_verification_attempt_grace_minutes',
                5,
            ));
            $graceExpiresAt = now()->addMinutes($attemptGraceMinutes);
            CustomerVerificationCode::query()
                ->whereIn('id', $pendingCodes->pluck('id'))
                ->where('expires_at', '<', $graceExpiresAt)
                ->update(['expires_at' => $graceExpiresAt]);

            $latest->refresh();
            $attempts = (int) $latest->attempts + 1;
            $preservedExpiresAt = $latest->expires_at->greaterThan($graceExpiresAt)
                ? $latest->expires_at
                : $graceExpiresAt;
            CustomerVerificationCode::query()->whereKey($latest->id)->update([
                'attempts' => $attempts,
                'expires_at' => $preservedExpiresAt,
                'updated_at' => now(),
            ]);
            $latest->refresh();
            if ($attempts >= $maxAttempts) {
                $latest->delete();
            }

            $remaining = max(0, $maxAttempts - $attempts);
            $message = $remaining > 0
                ? "A megadott kód hibás. Még {$remaining} próbálkozásod maradt ezzel a kóddal."
                : 'A megadott kód túl sokszor volt hibás. Kérj új kódot, vagy használd egy másik, még érvényes e-mailben kapott kódodat.';
            return ['pending' => null, 'message' => $message];
        });

        if ($result['pending'] instanceof CustomerVerificationCode) {
            return $result['pending'];
        }

        throw ValidationException::withMessages(['code' => [$result['message']]]);
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
