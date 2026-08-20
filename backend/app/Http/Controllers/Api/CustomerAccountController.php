<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerAccount;
use App\Models\CustomerVerificationCode;
use App\Rules\PersonName;
use App\Services\BookingMailService;
use App\Services\DataRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerAccountController extends Controller
{
    private function account(Request $request): CustomerAccount
    {
        $account = $request->user();
        abort_unless($account instanceof CustomerAccount && $account->tokenCan('user'), 403);
        return $account->loadMissing('business');
    }

    public function me(Request $request, CustomerAuthController $auth): JsonResponse
    {
        return response()->json(['account' => $auth->payload($this->account($request))]);
    }

    public function bookings(Request $request, BookingMailService $mail): JsonResponse
    {
        $account = $this->account($request);
        $bookings = $account->bookings()
            ->with('service:id,name,active')
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'service_name' => $booking->service_name,
                'date' => $booking->date->format('Y-m-d'),
                'start_time' => substr((string) $booking->start_time, 0, 5),
                'end_time' => substr((string) $booking->end_time, 0, 5),
                'status' => $booking->status,
                'manage_url' => $booking->manage_token_expires_at?->isFuture() ? $mail->manageUrl($booking) : null,
            ]);

        return response()->json(['data' => $bookings]);
    }

    public function update(Request $request, CustomerAuthController $auth): JsonResponse
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'name' => ['required', 'string', new PersonName()],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $account->update($validated);
        $account->bookings()->whereNull('anonymized_at')->update([
            'customer_name' => $account->name,
            'customer_phone' => $account->phone,
        ]);
        $account->business->customerProfiles()->where('email', $account->email)->update([
            'name' => $account->name,
            'phone' => $account->phone,
        ]);

        return response()->json(['account' => $auth->payload($account->fresh('business'))]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        if (! Hash::check($validated['current_password'], $account->password)) {
            throw ValidationException::withMessages(['current_password' => ['A jelenlegi jelszó hibás.']]);
        }
        $currentTokenId = $account->currentAccessToken()?->id;
        DB::transaction(function () use ($account, $validated, $currentTokenId): void {
            $account->update(['password' => $validated['password'], 'password_changed_at' => now()]);
            $account->tokens()->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();
        });

        return response()->json(['message' => 'A jelszavad frissült, a többi munkamenetet kijelentkeztettük.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $currentId = $account->currentAccessToken()?->id;
        return response()->json(['data' => $account->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'current' => $token->id === $currentId,
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ])]);
    }

    public function destroySession(Request $request, int $tokenId): JsonResponse
    {
        $account = $this->account($request);
        $token = $account->tokens()->whereKey($tokenId)->firstOrFail();
        $wasCurrent = $token->id === $account->currentAccessToken()?->id;
        $token->delete();
        return response()->json(['message' => 'A munkamenetet kijelentkeztettük.', 'current' => $wasCurrent]);
    }

    public function destroy(Request $request, DataRetentionService $retention): JsonResponse
    {
        $account = $this->account($request);
        $today = CarbonImmutable::now($account->business->timezone)->toDateString();
        $hasFuture = $account->bookings()
            ->where('status', Booking::STATUS_BOOKED)
            ->whereDate('date', '>=', $today)
            ->exists();
        if ($hasFuture) {
            return response()->json([
                'message' => 'A fiók törlése előtt mondd le az aktív, jövőbeli foglalásaidat.',
            ], 409);
        }

        DB::transaction(function () use ($account, $retention): void {
            $account->bookings()->orderBy('id')->each(function (Booking $booking) use ($retention): void {
                $retention->anonymizeBooking($booking);
            });
            $account->business->customerProfiles()->where('email', $account->email)->delete();
            $account->tokens()->delete();
            CustomerVerificationCode::query()
                ->where('business_id', $account->business_id)
                ->where('email', $account->email)
                ->delete();
            $account->delete();
        });

        return response()->json(['message' => 'A fiókot és a kapcsolódó személyes adatokat töröltük.']);
    }
}
