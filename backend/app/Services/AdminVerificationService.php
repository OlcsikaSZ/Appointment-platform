<?php

namespace App\Services;

use App\Mail\AdminVerificationCodeMail;
use App\Models\AdminVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminVerificationService
{
    public function issue(User $user, string $purpose, string $email): int
    {
        $email = mb_strtolower(trim($email));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $validMinutes = max(5, (int) config('appointment.admin_verification_code_minutes', 15));

        DB::transaction(function () use ($user, $purpose, $email, $code, $validMinutes): void {
            $query = AdminVerificationCode::query()
                ->where('user_id', $user->id)
                ->where('email', $email)
                ->where('purpose', $purpose);

            (clone $query)->where('expires_at', '<=', now())->delete();

            AdminVerificationCode::query()->create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($validMinutes),
            ]);

            $maxActiveCodes = max(1, (int) config('appointment.admin_verification_active_codes', 3));
            $obsoleteIds = (clone $query)->latest('id')->pluck('id')->slice($maxActiveCodes)->values();
            if ($obsoleteIds->isNotEmpty()) {
                AdminVerificationCode::query()->whereIn('id', $obsoleteIds)->delete();
            }
        });

        Mail::to($email)->queue(new AdminVerificationCodeMail(
            $user->business,
            $code,
            $purpose,
            $validMinutes,
        ));

        return $validMinutes;
    }

    public function validate(User $user, string $purpose, string $email, string $code): AdminVerificationCode
    {
        $email = mb_strtolower(trim($email));

        $result = DB::transaction(function () use ($user, $purpose, $email, $code): array {
            $maxAttempts = max(1, (int) config('appointment.admin_verification_max_attempts', 5));
            $query = AdminVerificationCode::query()
                ->where('user_id', $user->id)
                ->where('email', $email)
                ->where('purpose', $purpose);

            (clone $query)->where('expires_at', '<=', now())->delete();
            $pendingCodes = (clone $query)
                ->where('attempts', '<', $maxAttempts)
                ->latest('id')
                ->lockForUpdate()
                ->get();

            if ($pendingCodes->isEmpty()) {
                return ['pending' => null, 'message' => 'A kód lejárt vagy nem létezik. Kérj új kódot.'];
            }

            foreach ($pendingCodes as $pending) {
                if (Hash::check($code, $pending->code_hash)) {
                    return ['pending' => $pending, 'message' => null];
                }
            }

            $latest = $pendingCodes->first();
            $attemptGraceMinutes = max(1, (int) config(
                'appointment.admin_verification_attempt_grace_minutes',
                5,
            ));
            $graceExpiresAt = now()->addMinutes($attemptGraceMinutes);

            AdminVerificationCode::query()
                ->whereIn('id', $pendingCodes->pluck('id'))
                ->where('expires_at', '<', $graceExpiresAt)
                ->update(['expires_at' => $graceExpiresAt]);

            $latest->refresh();
            $attempts = (int) $latest->attempts + 1;
            $preservedExpiresAt = $latest->expires_at->greaterThan($graceExpiresAt)
                ? $latest->expires_at
                : $graceExpiresAt;

            AdminVerificationCode::query()->whereKey($latest->id)->update([
                'attempts' => $attempts,
                'expires_at' => $preservedExpiresAt,
                'updated_at' => now(),
            ]);

            if ($attempts >= $maxAttempts) {
                AdminVerificationCode::query()->whereKey($latest->id)->delete();
            }

            $remaining = max(0, $maxAttempts - $attempts);
            $message = $remaining > 0
                ? "A megadott kód hibás. Még {$remaining} próbálkozásod maradt ezzel a kóddal."
                : 'A megadott kód túl sokszor volt hibás. Kérj új kódot.';

            return ['pending' => null, 'message' => $message];
        });

        if ($result['pending'] instanceof AdminVerificationCode) {
            return $result['pending'];
        }

        throw ValidationException::withMessages(['code' => [$result['message']]]);
    }

    public function forget(User $user, string $purpose): void
    {
        AdminVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->delete();
    }
}
